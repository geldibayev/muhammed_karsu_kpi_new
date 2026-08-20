<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Models\Datum;
use App\Models\Report;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillFixedPerResourcePoints extends Command
{
    protected $signature = 'kpi:criteria:backfill-fixed-resource-points
                            {--criterion= : Faqat ko\'rsatilgan kriteriya kodini qayta hisoblash}
                            {--report= : Faqat ko\'rsatilgan hisobot ID doirasida qayta hisoblash}
                            {--dry-run : Bazaga yozmasdan tuzatiladigan resurslar sonini ko‘rsatish}';

    protected $description = 'Qat’iy resurs balli mezonlaridagi accepted resurslarni kategoriya bo‘yicha qayta hisoblaydi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $criterionCode = trim((string) $this->option('criterion'));
        $reportId = $this->reportId();

        if ($reportId === false) {
            return self::FAILURE;
        }

        if ($criterionCode !== '' && ! FixedPerResourceHumanReviewCriterionRule::supports($criterionCode)) {
            $this->error("Qat'iy resurs balli qoidasi topilmadi: {$criterionCode}.");

            return self::FAILURE;
        }

        $changedCount = 0;
        $reportIds = collect();

        $this->acceptedDataQuery($criterionCode !== '' ? $criterionCode : null, $reportId)
            ->lazyById(200)
            ->each(function (Datum $datum) use (&$changedCount, $dryRun, $reportIds): void {
                $targetPoint = FixedPerResourceHumanReviewCriterionRule::pointFor(
                    (string) $datum->criterion?->code,
                    (string) $datum->user?->degree,
                );

                if ($targetPoint === null) {
                    return;
                }

                $reportIds->push($datum->criterion?->report_id);

                if (abs($datum->point - $targetPoint) < 0.00005) {
                    return;
                }

                $changedCount++;

                if ($dryRun) {
                    return;
                }

                $this->updateDatumPoint($datum->getKey(), $targetPoint);
            });

        if ($dryRun) {
            $this->info("Qayta hisoblanadigan accepted resurslar: {$changedCount}");

            return self::SUCCESS;
        }

        Report::query()
            ->whereIn('id', $reportIds->filter()->unique()->values())
            ->orderBy('id')
            ->get()
            ->each(fn (Report $report): mixed => $recalculateReportPoints->handle($report));

        $this->info("Qayta hisoblangan accepted resurslar: {$changedCount}");

        return self::SUCCESS;
    }

    private function acceptedDataQuery(?string $criterionCode, ?int $reportId): Builder
    {
        return Datum::query()
            ->where('status', 'accepted')
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query
                    ->whereIn('code', $criterionCode === null
                        ? FixedPerResourceHumanReviewCriterionRule::criterionCodes()
                        : [$criterionCode])
                    ->when($reportId !== null, fn (Builder $query): Builder => $query
                        ->where('report_id', $reportId)),
            )
            ->with(['criterion:id,code,report_id', 'user:id,degree'])
            ->orderBy('id');
    }

    private function reportId(): int|false|null
    {
        $reportOption = trim((string) $this->option('report'));

        if ($reportOption === '') {
            return null;
        }

        $reportId = filter_var($reportOption, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($reportId === false) {
            $this->error('Hisobot ID musbat butun son bo‘lishi kerak.');

            return false;
        }

        if (! Report::query()->whereKey($reportId)->exists()) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return false;
        }

        return $reportId;
    }

    private function updateDatumPoint(int $datumId, float $targetPoint): void
    {
        DB::transaction(function () use ($datumId, $targetPoint): void {
            $datum = Datum::query()
                ->with(['criterion:id,code', 'user:id,degree'])
                ->lockForUpdate()
                ->find($datumId);
            $currentTargetPoint = $datum === null
                ? null
                : FixedPerResourceHumanReviewCriterionRule::pointFor(
                    (string) $datum->criterion?->code,
                    (string) $datum->user?->degree,
                );

            if ($datum === null
                || $datum->status !== 'accepted'
                || $currentTargetPoint === null
                || abs($datum->point - $currentTargetPoint) < 0.00005
                || abs($currentTargetPoint - $targetPoint) >= 0.00005) {
                return;
            }

            $oldPoint = $datum->point;
            $datum->update(['point' => $currentTargetPoint]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Qat’iy kategoriya qoidasi bo‘yicha ball qayta hisoblandi. '
                    .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                    .'Yangi ball: '.number_format($currentTargetPoint, 4, '.', '').'.',
                'message_type' => 'fixed_resource_point_recalculated',
            ]);
        }, 3);
    }
}
