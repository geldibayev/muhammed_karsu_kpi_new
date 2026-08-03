<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Support\InternationalCooperationCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckInternationalCooperationAiEvaluations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:recheck-international-cooperation-ai-evaluations
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--datum=* : Faqat ko‘rsatilgan datum IDlarini qayta tekshirish}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}
                            {--apply : Resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '2.1.6 mezonidagi oldingi AI xulosalarini tuzatilgan qoida bilan qayta tekshiradi';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();
        $datumIds = $this->validatedDatumIds();

        if ($reportId === false || $limit === false || $datumIds === false) {
            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $this->candidateCount($report, $datumIds, $limit);
        $this->info("2.1.6 mezoni bo‘yicha qayta tekshiruvga mos resurslar: {$candidateCount}");

        if (! $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi.');

            return self::SUCCESS;
        }

        $queued = 0;
        $failedDispatches = 0;

        foreach ($this->candidates($report, $datumIds, $limit) as $candidate) {
            $queuedDatum = $this->markForRecheck((int) $candidate->getKey(), $report);

            if ($queuedDatum === null) {
                continue;
            }

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                )->afterCommit();
                $queued++;
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $queuedDatum->histories()->create([
                    'user_id' => $queuedDatum->user_id,
                    'type' => 'warning',
                    'message' => '2.1.6 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($queued > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("2.1.6 mezoni bo‘yicha AI qayta tekshiruviga qo‘yildi: {$queued}");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatches}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param list<int> $datumIds */
    private function candidateCount(Report $report, array $datumIds, ?int $limit): int
    {
        $candidateCount = 0;

        foreach ($this->candidates($report, $datumIds, $limit) as $datum) {
            $candidateCount++;
        }

        return $candidateCount;
    }

    /**
     * @param  list<int>  $datumIds
     * @return iterable<int, Datum>
     */
    private function candidates(Report $report, array $datumIds, ?int $limit): iterable
    {
        $candidateCount = 0;

        foreach ($this->candidateQuery($report, $datumIds)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if (! $this->hasCompletedAiEvaluation($datum)) {
                continue;
            }

            yield $datum;
            $candidateCount++;

            if ($limit !== null && $candidateCount >= $limit) {
                return;
            }
        }
    }

    /** @param list<int> $datumIds */
    private function candidateQuery(Report $report, array $datumIds): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->withMax([
                'histories as last_ai_evaluation_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation'),
            ], 'id')
            ->withMax([
                'histories as last_ai_queue_id' => fn (Builder $query): Builder => $query
                    ->whereIn('message_type', ['submission_created', 'ai_queued']),
            ], 'id')
            ->whereIn('data.status', ['accepted', 'cancelled', 'checking'])
            ->when($datumIds !== [], fn (Builder $query): Builder => $query->whereKey($datumIds))
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('code', InternationalCooperationCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereHas('histories', fn (Builder $query): Builder => $query
                ->where('message_type', 'ai_evaluation'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->whereIn('message_type', [
                    'manual_review_approved',
                    'manual_review_rejected',
                    'h_index_review_approved',
                    'criterion_transferred',
                    'ai_international_cooperation_recheck_queued',
                ]));
    }

    private function hasCompletedAiEvaluation(Datum $datum): bool
    {
        return (int) ($datum->last_ai_evaluation_id ?? 0) > (int) ($datum->last_ai_queue_id ?? 0);
    }

    private function markForRecheck(int $datumId, Report $report): ?Datum
    {
        return DB::transaction(function () use ($datumId, $report): ?Datum {
            $datum = Datum::query()
                ->with('criterion:id,report_id,code,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || ! in_array($datum->status, ['accepted', 'cancelled', 'checking'], true)
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== InternationalCooperationCriterionRule::CODE
                || $datum->criterion->checking !== 'ai') {
                return null;
            }

            $history = $datum->histories()
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
                ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
                ->selectRaw("MAX(CASE WHEN message_type IN ('manual_review_approved', 'manual_review_rejected', 'h_index_review_approved', 'criterion_transferred', 'ai_international_cooperation_recheck_queued') THEN id ELSE 0 END) AS excluded_history_id")
                ->first();

            if ((int) $history?->last_evaluation_id <= (int) $history?->last_queue_id
                || (int) $history?->excluded_history_id > 0) {
                return null;
            }

            $datum->update([
                'status' => 'checking',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $datum->histories()->createMany([
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs 2.1.6 mezonining tuzatilgan AI qoidasi bilan qayta tekshiruvga belgilandi.',
                    'message_type' => 'ai_international_cooperation_recheck_queued',
                ],
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            return $datum;
        }, 3);
    }

    private function validatedLimit(): int|false|null
    {
        $value = $this->option('limit');

        if ($value === null) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($limit === false) {
            $this->error('--limit musbat butun son bo‘lishi kerak.');
        }

        return $limit;
    }

    /** @return list<int>|false */
    private function validatedDatumIds(): array|false
    {
        $ids = [];

        foreach ((array) $this->option('datum') as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($id === false) {
                $this->error('--datum qiymatlari musbat butun son bo‘lishi kerak.');

                return false;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
