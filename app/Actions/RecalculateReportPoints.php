<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Point;
use App\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class RecalculateReportPoints
{
    public function handle(Report $report): void
    {
        Cache::lock("reports:{$report->getKey()}:points-rebuild", 120)
            ->block(5, function () use ($report): void {
                DB::transaction(function () use ($report): void {
                    Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

                    $this->rebuildCriterionPoints($report);
                    $this->rebuildFinalPoints($report);
                }, attempts: 5);
            });
    }

    private function rebuildCriterionPoints(Report $report): void
    {
        $aggregates = Datum::query()
            ->select(['user_id', 'criterion_id'])
            ->selectRaw('SUM(point) as point')
            ->selectRaw('COUNT(*) as files')
            ->where('status', 'accepted')
            ->whereHas('criterion', fn ($query) => $query->where('report_id', $report->getKey()))
            ->groupBy('user_id', 'criterion_id')
            ->get();

        CriterionPoint::query()->where('report_id', $report->getKey())->delete();

        $rows = $aggregates->map(fn (Datum $aggregate): array => [
            'user_id' => $aggregate->user_id,
            'criterion_id' => $aggregate->criterion_id,
            'report_id' => $report->getKey(),
            'point' => max(0, (float) $aggregate->point),
            'files' => (int) $aggregate->files,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            CriterionPoint::query()->upsert(
                $rows,
                ['report_id', 'user_id', 'criterion_id'],
                ['point', 'files', 'updated_at'],
            );
        }
    }

    private function rebuildFinalPoints(Report $report): void
    {
        $criteria = Criterion::query()
            ->where('report_id', $report->getKey())
            ->whereNotNull('parent_id')
            ->with([
                'criterionEvaluations:id,criterion_id,evaluation,has,score',
                'criterionPoints' => fn ($query) => $query
                    ->where('report_id', $report->getKey())
                    ->with('user:id,degree'),
            ])
            ->get();

        Point::query()->where('report_id', $report->getKey())->delete();

        $rows = $criteria->flatMap(fn (Criterion $criterion): Collection => $this->pointRows($report, $criterion))->all();

        if ($rows !== []) {
            Point::query()->upsert(
                $rows,
                ['report_id', 'user_id', 'criterion_id'],
                ['point', 'updated_at'],
            );
        }
    }

    /** @return Collection<int, array<string, int|float|Carbon>> */
    private function pointRows(Report $report, Criterion $criterion): Collection
    {
        $highestRawPoint = max(0, (float) $criterion->criterionPoints->max('point'));

        return $criterion->criterionPoints
            ->filter(fn (CriterionPoint $criterionPoint): bool => $criterionPoint->user !== null)
            ->map(function (CriterionPoint $criterionPoint) use ($report, $criterion, $highestRawPoint): array {
                $evaluation = $criterion->criterionEvaluations
                    ->firstWhere('evaluation', $criterionPoint->user->degree);
                $maximumPoint = $evaluation?->has === '1' ? max(0, (float) $evaluation->score) : 0;
                $rawPoint = max(0, (float) $criterionPoint->point);

                $calculatedPoint = match ((int) $criterion->formula_id) {
                    1 => $highestRawPoint > 0 ? $maximumPoint * ($rawPoint / $highestRawPoint) : 0,
                    2 => min($rawPoint, $maximumPoint),
                    3 => $rawPoint,
                    default => throw new UnexpectedValueException(
                        "Unknown scoring formula [{$criterion->formula_id}] for criterion [{$criterion->getKey()}].",
                    ),
                };

                return [
                    'user_id' => $criterionPoint->user_id,
                    'criterion_id' => $criterion->getKey(),
                    'report_id' => $report->getKey(),
                    'point' => round($calculatedPoint, 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });
    }
}
