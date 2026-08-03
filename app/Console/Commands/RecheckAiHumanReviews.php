<?php

namespace App\Console\Commands;

use App\Actions\DescribeAiFailure;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckAiHumanReviews extends Command
{
    protected $signature = 'kpi:ai:recheck-human-reviews
                            {--limit=0 : Qayta tekshiriladigan maksimum resurslar soni (0 = barchasi)}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan nomzodlar sonini ko‘rsatish}
                            {--urls-only : Faqat URL resurslarini qayta navbatga qo‘yish}
                            {--force : Tasdiqlash so‘ramasdan qayta navbatga qo‘yish}';

    protected $description = 'AI inson tekshiruviga qoldirgan resurslarni yangi qaror qoidasi bilan qayta baholaydi';

    public function handle(DescribeAiFailure $describeAiFailure): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $candidateCount = $this->candidateCount($limit);

        if ($dryRun) {
            $this->info("Qayta AI tekshiruviga mos inson tekshiruvidagi resurslar: {$candidateCount}");

            return self::SUCCESS;
        }

        if ($candidateCount === 0) {
            $this->info('Qayta AI tekshiruviga mos resurs topilmadi.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("{$candidateCount} ta resursni qayta AI tekshiruviga yuborasizmi?", false)) {
            $this->warn('Qayta navbatga qo‘yish bekor qilindi.');

            return self::SUCCESS;
        }

        $queuedCount = 0;
        $failedCount = 0;

        foreach ($this->candidates($limit) as $datum) {
            $queuedDatum = $this->markAsQueued($datum->getKey());

            if ($queuedDatum === null) {
                continue;
            }

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $datum->getKey(),
                    $queuedDatum['criterion_id'],
                )->afterCommit();
                $queuedCount++;
            } catch (Throwable $exception) {
                $failedCount++;
                $this->recordDispatchFailure(
                    $datum->getKey(),
                    $queuedDatum,
                    $describeAiFailure->handle($exception),
                );
                Log::error('AI inson tekshiruvidagi resurs qayta navbatga qo‘yilmadi.', [
                    'datum_id' => $datum->getKey(),
                    'criterion_id' => $queuedDatum['criterion_id'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Qayta AI tekshiruviga qo‘yildi: {$queuedCount}");

        if ($failedCount > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function candidateCount(int $limit): int
    {
        $candidateCount = 0;

        foreach ($this->candidates($limit) as $datum) {
            $candidateCount++;
        }

        return $candidateCount;
    }

    /** @return iterable<int, Datum> */
    private function candidates(int $limit): iterable
    {
        $candidateCount = 0;

        foreach ($this->candidateQuery()->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if (! $this->shouldRecheck($datum)) {
                continue;
            }

            yield $datum;
            $candidateCount++;

            if ($limit > 0 && $candidateCount >= $limit) {
                return;
            }
        }
    }

    private function candidateQuery(): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.criterion_id'])
            ->withMax([
                'histories as last_ai_evaluation_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation')
                    ->where('type', 'warning'),
            ], 'id')
            ->withMax([
                'histories as last_ai_queue_id' => fn (Builder $query): Builder => $query
                    ->whereIn('message_type', ['submission_created', 'ai_queued']),
            ], 'id')
            ->withMax([
                'histories as last_criterion_transfer_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'criterion_transferred'),
            ], 'id')
            ->where('data.status', 'checking')
            ->when(
                $this->option('urls-only'),
                fn (Builder $query): Builder => $query->where('data.material->type', 'url'),
            )
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            )
            ->whereHas(
                'histories',
                fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation')
                    ->where('type', 'warning'),
            )
            ->whereDoesntHave(
                'histories',
                fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_decision_rule_recheck_queued'),
            );
    }

    private function shouldRecheck(Datum $datum): bool
    {
        $lastEvaluationId = (int) ($datum->last_ai_evaluation_id ?? 0);

        return $lastEvaluationId > (int) ($datum->last_ai_queue_id ?? 0)
            && $lastEvaluationId > (int) ($datum->last_criterion_transfer_id ?? 0);
    }

    /**
     * @return array{criterion_id: int, reviewer_hemis_id: int|null}|null
     */
    private function markAsQueued(int $datumId): ?array
    {
        return DB::transaction(function () use ($datumId): ?array {
            $datum = Datum::query()
                ->with('criterion:id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->criterion?->checking !== 'ai') {
                return null;
            }

            $history = $datum->histories()
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' AND type = 'warning' THEN id ELSE 0 END) AS last_evaluation_id")
                ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'criterion_transferred' THEN id ELSE 0 END) AS last_transfer_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_decision_rule_recheck_queued' THEN id ELSE 0 END) AS last_rule_recheck_id")
                ->first();

            if ((int) $history?->last_evaluation_id <= (int) $history?->last_queue_id
                || (int) $history?->last_evaluation_id <= (int) $history?->last_transfer_id
                || (int) $history?->last_rule_recheck_id > 0) {
                return null;
            }

            $previousReviewerHemisId = $datum->reviewer_hemis_id;

            $datum->update([
                'point' => 0,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'reason' => 'AI xulosasi yangi qaror qoidasi bo‘yicha qayta tekshirilmoqda.',
                'reviewer_hemis_id' => null,
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Oldingi AI xulosasi yangi rad etish qoidasi bo‘yicha qayta AI navbatiga qo‘yildi.',
                'message_type' => 'ai_queued',
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Resurs yangilangan AI qaror qoidasi bilan bir martalik qayta tekshiruvga belgilandi.',
                'message_type' => 'ai_decision_rule_recheck_queued',
            ]);

            return [
                'criterion_id' => $datum->criterion_id,
                'reviewer_hemis_id' => $previousReviewerHemisId,
            ];
        }, 3);
    }

    /**
     * @param  array{criterion_id: int, reviewer_hemis_id: int|null}  $queuedDatum
     */
    private function recordDispatchFailure(
        int $datumId,
        array $queuedDatum,
        string $reason,
    ): void {
        DB::transaction(function () use ($datumId, $queuedDatum, $reason): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->criterion_id !== $queuedDatum['criterion_id']
                || $datum->status !== 'checking') {
                return;
            }

            $datum->update([
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => $queuedDatum['reviewer_hemis_id'],
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => $reason,
                'message_type' => 'ai_failed',
            ]);
        }, 3);
    }
}
