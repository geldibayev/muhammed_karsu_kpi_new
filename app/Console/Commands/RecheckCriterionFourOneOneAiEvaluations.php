<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Enums\DatumStatus;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckCriterionFourOneOneAiEvaluations extends Command
{
    private const RECHECK_MESSAGE_TYPE = 'ai_four_one_one_reference_recheck_queued';

    protected $signature = 'kpi:criteria:recheck-4-1-1-resources
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}
                            {--apply : Resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}';

    protected $description = '4.1.1 mezonidagi barcha resurslarni ma’lumotnomani rad etuvchi AI qoidasi bilan qayta tekshiradi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();

        if ($reportId === false || $limit === false) {
            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $this->candidateQuery($report)->count();
        $plannedCount = $limit === null ? $candidateCount : min($candidateCount, $limit);
        $this->info("4.1.1 mezoni bo‘yicha qayta tekshiriladigan resurslar: {$candidateCount}");

        if (! $this->option('apply')) {
            $this->warn("Dry-run: {$plannedCount} ta resurs qayta AI tekshiruviga tushadi. O‘zgarish kiritilmadi.");

            return self::SUCCESS;
        }

        $transitioned = 0;
        $queued = 0;
        $failedDispatches = 0;

        foreach ($this->candidateQuery($report)->lazyById(200) as $candidate) {
            if ($limit !== null && $transitioned >= $limit) {
                break;
            }

            $queuedDatum = $this->markForRecheck((int) $candidate->getKey(), $report);

            if ($queuedDatum === null) {
                continue;
            }

            $transitioned++;

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
                    'message' => '4.1.1 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($transitioned > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("Checking holatiga o‘tkazildi: {$transitioned}");
        $this->info("AI navbatiga muvaffaqiyatli qo‘yildi: {$queued}");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatches}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function candidateQuery(Report $report): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->whereIn('data.status', [
                DatumStatus::Received->value,
                DatumStatus::Checking->value,
                DatumStatus::Accepted->value,
                DatumStatus::Cancelled->value,
            ])
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->where('message_type', self::RECHECK_MESSAGE_TYPE));
    }

    private function markForRecheck(int $datumId, Report $report): ?Datum
    {
        return DB::transaction(function () use ($datumId, $report): ?Datum {
            $datum = Datum::query()
                ->with('criterion:id,report_id,code,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || ! in_array($datum->status, [
                    DatumStatus::Received->value,
                    DatumStatus::Checking->value,
                    DatumStatus::Accepted->value,
                    DatumStatus::Cancelled->value,
                ], true)
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE
                || $datum->criterion->checking !== 'ai'
                || $datum->histories()->where('message_type', self::RECHECK_MESSAGE_TYPE)->exists()) {
                return null;
            }

            $datum->update([
                'status' => DatumStatus::Checking->value,
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
            $datum->histories()->createMany([
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs 4.1.1 mezonining ma’lumotnomani rad etuvchi AI qoidasi bilan qayta tekshiruvga belgilandi.',
                    'message_type' => self::RECHECK_MESSAGE_TYPE,
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
}
