<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Support\IndustryFundingCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckIndustryFundingAiEvaluations extends Command
{
    protected $signature = 'kpi:recheck-industry-funding-ai-evaluations
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}
                            {--apply : Resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}';

    protected $description = '3.1.13 mezonidagi eski AI xulosalarini summa va hammualliflar soni asosida qayta tekshiradi';

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

        $candidateCount = $this->candidates($report, $limit)->count();
        $this->info("3.1.13 mezoni bo‘yicha qayta tekshiruvga mos resurslar: {$candidateCount}");

        if (! $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi.');

            return self::SUCCESS;
        }

        $queued = 0;
        $failedDispatches = 0;

        foreach ($this->candidates($report, $limit) as $candidate) {
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
                    'message' => '3.1.13 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($queued > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("3.1.13 mezoni bo‘yicha AI qayta tekshiruviga qo‘yildi: {$queued}");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatches}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Datum> */
    private function candidates(Report $report, ?int $limit): Collection
    {
        return $this->candidateQuery($report)
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get();
    }

    private function candidateQuery(Report $report): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('code', IndustryFundingCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->where('message_type', 'ai_industry_funding_recheck_queued'))
            ->orderBy('data.id');
    }

    private function markForRecheck(int $datumId, Report $report): ?Datum
    {
        return DB::transaction(function () use ($datumId, $report): ?Datum {
            $datum = Datum::query()
                ->with('criterion:id,report_id,code,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== IndustryFundingCriterionRule::CODE
                || $datum->criterion->checking !== 'ai'
                || $datum->histories()
                    ->where('message_type', 'ai_industry_funding_recheck_queued')
                    ->exists()) {
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
                'received_amount' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $datum->histories()->createMany([
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs 3.1.13 mezonining summa va hammualliflar qoidasida qayta tekshiruvga belgilandi.',
                    'message_type' => 'ai_industry_funding_recheck_queued',
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
