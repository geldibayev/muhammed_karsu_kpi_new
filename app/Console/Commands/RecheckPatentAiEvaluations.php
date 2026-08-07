<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Support\PatentCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckPatentAiEvaluations extends Command
{
    protected $signature = 'kpi:criteria:recheck-3-1-8-patents
                            {report : Qayta tekshiriladigan hisobot ID raqami}
                            {--datum=* : Faqat ko\'rsatilgan resurs IDlarini qayta tekshirish}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}
                            {--apply : Accepted resurslarni checking holatiga o\'tkazib AI navbatiga qo\'yish}';

    protected $description = '3.1.8 mezonidagi barcha eski accepted patent resurslarini yangi AI qoidalari bilan qayta tekshiradi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();
        $datumIds = $this->validatedDatumIds();

        if ($reportId === false || $limit === false || $datumIds === false) {
            if ($reportId === false) {
                $this->error('Hisobot ID musbat butun son bo\'lishi kerak.');
            }

            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $this->candidateQuery($report, $datumIds)->count();
        $plannedCount = $limit === null ? $candidateCount : min($candidateCount, $limit);
        $this->info("3.1.8 bo'yicha eski accepted patent resurslari: {$candidateCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn("Dry-run: {$plannedCount} ta resurs qayta AI tekshiruviga tushadi. O'zgarish kiritilmadi.");

            return self::SUCCESS;
        }

        $queuedCount = 0;
        $failedDispatchCount = 0;

        foreach ($this->candidateQuery($report, $datumIds)->lazyById(200, column: 'data.id', alias: 'id') as $candidate) {
            if ($limit !== null && $queuedCount >= $limit) {
                break;
            }

            $recheck = $this->markForRecheck((int) $candidate->getKey(), $report);

            if ($recheck === null) {
                continue;
            }

            ['datum' => $queuedDatum, 'original' => $original, 'started_history_id' => $startedHistoryId] = $recheck;
            $queuedCount++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                )->afterCommit();
                $this->recordSuccessfulDispatch($queuedDatum->getKey());
            } catch (Throwable $exception) {
                $failedDispatchCount++;
                report($exception);
                $this->restoreAfterDispatchFailure(
                    $queuedDatum->getKey(),
                    $startedHistoryId,
                    $original,
                );
            }
        }

        if ($queuedCount > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("3.1.8 patentlari bo'yicha AI qayta tekshiruviga qo'yildi: {$queuedCount}");

        if ($failedDispatchCount > 0) {
            $this->error("Navbatga qo'yishda xato: {$failedDispatchCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param list<int> $datumIds */
    private function candidateQuery(Report $report, array $datumIds): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->when($datumIds !== [], fn (Builder $query): Builder => $query->whereKey($datumIds))
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', PatentCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->where('message_type', 'ai_patent_recheck_queued'));
    }

    /**
     * @return array{
     *     datum: Datum,
     *     original: array<string, mixed>,
     *     started_history_id: int
     * }|null
     */
    private function markForRecheck(int $datumId, Report $report): ?array
    {
        return DB::transaction(function () use ($datumId, $report): ?array {
            $datum = Datum::query()
                ->with('criterion:id,report_id,code,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== PatentCriterionRule::CODE
                || $datum->criterion->checking !== 'ai'
                || $datum->histories()->where('message_type', 'ai_patent_recheck_queued')->exists()) {
                return null;
            }

            $original = $datum->only([
                'status',
                'point',
                'author_count',
                'page_count',
                'impact_factor',
                'publication_tier',
                'university_tier',
                'received_amount',
                'reason',
                'reviewer_hemis_id',
            ]);
            $datum->update([
                'status' => 'checking',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $startedHistory = $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '3.1.8 resursi patent turi, mualliflik va umumiy hisobot davri bo\'yicha qayta AI tekshiruviga belgilandi.',
                'message_type' => 'ai_patent_recheck_started',
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Resurs AI tekshiruv navbatiga qo\'yildi.',
                'message_type' => 'ai_queued',
            ]);

            return [
                'datum' => $datum,
                'original' => $original,
                'started_history_id' => (int) $startedHistory->getKey(),
            ];
        }, 3);
    }

    private function recordSuccessfulDispatch(int $datumId): void
    {
        DB::transaction(function () use ($datumId): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->histories()->where('message_type', 'ai_patent_recheck_queued')->exists()) {
                return;
            }

            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '3.1.8 patent resursi AI qayta tekshiruv navbatiga muvaffaqiyatli yuborildi.',
                'message_type' => 'ai_patent_recheck_queued',
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $original */
    private function restoreAfterDispatchFailure(
        int $datumId,
        int $startedHistoryId,
        array $original,
    ): void {
        DB::transaction(function () use ($datumId, $startedHistoryId, $original): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->histories()
                    ->where('message_type', 'ai_evaluation')
                    ->where('id', '>', $startedHistoryId)
                    ->exists()) {
                return;
            }

            $datum->update($original);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => '3.1.8 patent resursini qayta AI navbatiga yuborib bo\'lmadi. Oldingi accepted holati tiklandi.',
                'message_type' => 'ai_patent_recheck_dispatch_failed',
            ]);
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
            $this->error('--limit musbat butun son bo\'lishi kerak.');
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
                $this->error('--datum qiymatlari musbat butun son bo\'lishi kerak.');

                return false;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
