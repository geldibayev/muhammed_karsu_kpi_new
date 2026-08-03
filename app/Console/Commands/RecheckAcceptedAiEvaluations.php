<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckAcceptedAiEvaluations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:recheck-accepted-ai-evaluations
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--apply : Resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Oldin AI tasdiqlagan resurslarni yangi sana va ball qoidalari bilan qayta tekshiradi';

    /**
     * Execute the console command.
     */
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

        $this->info("Hisobot #{$report->getKey()}: {$candidateCount} ta AI tasdiqlagan resurs topildi.");

        if (! $this->option('apply')) {
            $this->warn("Dry-run: {$plannedCount} ta resurs qayta tekshiruvga tushadi. O‘zgarish kiritilmadi.");

            return self::SUCCESS;
        }

        $queued = 0;
        $failedDispatches = 0;

        foreach ($this->candidateQuery($report)->lazyById(200) as $candidate) {
            if ($limit !== null && $queued >= $limit) {
                break;
            }

            $queuedDatum = $this->markForRecheck($candidate->getKey(), $report);

            if ($queuedDatum === null) {
                continue;
            }

            $queued++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                );
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $queuedDatum->histories()->create([
                    'user_id' => $queuedDatum->user_id,
                    'type' => 'warning',
                    'message' => 'Qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($queued > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("{$queued} ta resurs checking holatiga o‘tkazildi va ballar qayta hisoblandi.");

        if ($failedDispatches > 0) {
            $this->warn("{$failedDispatches} ta resurs navbatga yuborilmadi; ular checking holatida qoldi.");
        }

        return $failedDispatches === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function candidateQuery(Report $report): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('checking', 'ai'))
            ->whereHas('histories', fn (Builder $query): Builder => $query
                ->where('message_type', 'ai_evaluation'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->whereIn('message_type', [
                    'manual_review_approved',
                    'manual_review_rejected',
                    'h_index_review_approved',
                    'criterion_transferred',
                    'ai_report_period_recheck_queued',
                ]));
    }

    private function markForRecheck(int $datumId, Report $report): ?Datum
    {
        return DB::transaction(function () use ($datumId, $report): ?Datum {
            $datum = Datum::query()
                ->with('criterion:id,report_id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->checking !== 'ai'
                || ! $datum->histories()->where('message_type', 'ai_evaluation')->exists()
                || $datum->histories()->whereIn('message_type', [
                    'manual_review_approved',
                    'manual_review_rejected',
                    'h_index_review_approved',
                    'criterion_transferred',
                    'ai_report_period_recheck_queued',
                ])->exists()) {
                return null;
            }

            $datum->update([
                'status' => 'checking',
                'point' => 0,
                'impact_factor' => null,
                'publication_tier' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $datum->histories()->createMany([
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => sprintf(
                        'Resurs %s–%s hisobot davri va yangilangan ball qoidalari bo‘yicha qayta tekshiruvga yuborildi.',
                        (string) config('kpi.report_period_start'),
                        (string) config('kpi.report_period_end'),
                    ),
                    'message_type' => 'ai_report_period_recheck_queued',
                ],
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            return $datum;
        }, attempts: 3);
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
