<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Models\Datum;
use App\Models\Report;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillCriterionOneTenPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:criteria:backfill-1-10-points
                            {report : Hisobot ID raqami}
                            {--apply : O‘zgarishlarni bazaga yozish va hisobot ballarini qayta hisoblash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '1.10 bo‘yicha kam berilgan accepted resurs ballarini foydalanuvchi toifasiga moslaydi';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($reportId === false) {
            $this->error('Hisobot ID musbat butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        if (! $this->acceptedDataQuery($report)->exists()) {
            $this->info('1.10 bo‘yicha tekshiriladigan accepted resurs topilmadi.');

            if ((bool) $this->option('apply')) {
                $recalculateReportPoints->handle($report);
            }

            return self::SUCCESS;
        }

        $candidateIds = $this->acceptedDataQuery($report)
            ->get()
            ->filter(fn (Datum $datum): bool => $this->isBelowTarget($datum))
            ->modelKeys();

        $candidateCount = count($candidateIds);
        $this->info("1.10 bo‘yicha toifa balliga yangilanadigan resurslar: {$candidateCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.');

            return self::SUCCESS;
        }

        $updatedCount = 0;

        foreach ($candidateIds as $datumId) {
            if ($this->updateDatumPoint((int) $datumId)) {
                $updatedCount++;
            }
        }

        $recalculateReportPoints->handle($report);
        $this->info("1.10 bo‘yicha toifa balliga yangilandi: {$updatedCount}");

        return self::SUCCESS;
    }

    private function acceptedDataQuery(Report $report): Builder
    {
        return Datum::query()
            ->where('status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', '1.10'))
            ->with(['criterion:id,code', 'user:id,degree'])
            ->orderBy('id');
    }

    private function isBelowTarget(Datum $datum): bool
    {
        $targetPoint = FixedPerResourceHumanReviewCriterionRule::pointFor(
            (string) $datum->criterion?->code,
            (string) $datum->user?->degree,
        );

        return $targetPoint !== null && $datum->point < $targetPoint - 0.00005;
    }

    private function updateDatumPoint(int $datumId): bool
    {
        return DB::transaction(function () use ($datumId): bool {
            $datum = Datum::query()
                ->with(['criterion:id,code', 'user:id,degree'])
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null || $datum->status !== 'accepted' || ! $this->isBelowTarget($datum)) {
                return false;
            }

            $targetPoint = FixedPerResourceHumanReviewCriterionRule::pointFor(
                (string) $datum->criterion?->code,
                (string) $datum->user?->degree,
            );

            if ($targetPoint === null) {
                return false;
            }

            $oldPoint = $datum->point;
            $datum->update(['point' => $targetPoint]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '1.10 mezoni bo‘yicha ball foydalanuvchi toifasiga moslandi. '
                    .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                    .'Yangi ball: '.number_format($targetPoint, 4, '.', '').'.',
                'message_type' => 'criterion_1_10_point_corrected',
            ]);

            return true;
        }, 3);
    }
}
