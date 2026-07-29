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

class QueuePendingAiEvaluations extends Command
{
    protected $signature = 'kpi:ai:queue-pending
                            {--limit=0 : Navbatga qo‘yiladigan maksimum resurslar soni (0 = barchasi)}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan faqat nomzodlar sonini ko‘rsatish}';

    protected $description = 'Hali AI natijasi yo‘q resurslarni idempotent tarzda AI navbatiga qo‘yadi';

    public function handle(DescribeAiFailure $describeAiFailure): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $candidateCount = 0;
        $queuedCount = 0;
        $failedCount = 0;

        foreach ($this->candidateQuery()->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if (! $this->shouldQueue($datum)) {
                continue;
            }

            $candidateCount++;

            if ($dryRun) {
                continue;
            }

            if ($limit > 0 && $queuedCount >= $limit) {
                break;
            }

            $criterionId = $this->markAsQueued($datum->getKey());

            if ($criterionId === null) {
                continue;
            }

            try {
                ProcessAiDatumEvaluation::dispatch($datum->getKey(), $criterionId);
                $queuedCount++;
            } catch (Throwable $exception) {
                $failedCount++;
                $this->recordDispatchFailure(
                    $datum->getKey(),
                    $criterionId,
                    $describeAiFailure->handle($exception),
                );
                Log::error('Backfill AI jobi navbatga qo‘yilmadi.', [
                    'datum_id' => $datum->getKey(),
                    'criterion_id' => $criterionId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($dryRun) {
            $this->info("AI navbatiga qo‘yilishi kerak bo‘lgan resurslar: {$candidateCount}");

            return self::SUCCESS;
        }

        $this->info("AI navbatiga qo‘yildi: {$queuedCount}");

        if ($failedCount > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function candidateQuery(): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id', 'data.status'])
            ->withMax([
                'histories as last_ai_evaluation_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation'),
            ], 'id')
            ->withMax([
                'histories as last_ai_failure_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_failed'),
            ], 'id')
            ->withMax([
                'histories as last_ai_queue_id' => fn (Builder $query): Builder => $query
                    ->whereIn('message_type', ['submission_created', 'ai_queued']),
            ], 'id')
            ->whereIn('status', ['received', 'checking'])
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            )
            ->whereDoesntHave(
                'histories',
                fn (Builder $query): Builder => $query->where('message_type', 'ai_evaluation'),
            );
    }

    private function shouldQueue(Datum $datum): bool
    {
        $lastQueueId = (int) ($datum->last_ai_queue_id ?? 0);
        $lastFailureId = (int) ($datum->last_ai_failure_id ?? 0);

        return $lastQueueId === 0 || $lastFailureId > $lastQueueId;
    }

    private function markAsQueued(int $datumId): ?int
    {
        return DB::transaction(function () use ($datumId): ?int {
            $datum = Datum::query()
                ->with('criterion:id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || ! in_array($datum->status, ['received', 'checking'], true)
                || $datum->criterion?->checking !== 'ai') {
                return null;
            }

            $history = $datum->histories()
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
                ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
                ->first();

            if ((int) $history?->last_evaluation_id > 0
                || (int) $history?->last_queue_id > (int) $history?->last_failure_id) {
                return null;
            }

            $datum->update([
                'status' => 'checking',
                'reviewer_hemis_id' => null,
                'reason' => 'AI tahlili navbatga qo‘yildi.',
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                'message_type' => 'ai_queued',
            ]);

            return $datum->criterion_id;
        }, 3);
    }

    private function recordDispatchFailure(int $datumId, int $criterionId, string $reason): void
    {
        DB::transaction(function () use ($datumId, $criterionId, $reason): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->criterion_id !== $criterionId
                || $datum->status !== 'checking') {
                return;
            }

            $datum->update(['reason' => $reason]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => $reason,
                'message_type' => 'ai_failed',
            ]);
        }, 3);
    }
}
