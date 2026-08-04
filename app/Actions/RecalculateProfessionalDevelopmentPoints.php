<?php

namespace App\Actions;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Support\ProfessionalDevelopmentCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecalculateProfessionalDevelopmentPoints
{
    public function __construct(
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    /**
     * @return array{total: int, changes: int, conflicts: array<int, int>, sources: array<string, int>}
     */
    public function handle(Report $report, bool $apply = false): array
    {
        if (! $apply) {
            $analysis = $this->analyse($report);
            unset($analysis['rows']);

            return $analysis;
        }

        $result = DB::transaction(function () use ($report): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $analysis = $this->analyse($report, true);

            if ($analysis['conflicts'] !== []) {
                throw new RuntimeException(
                    'Saqlangan Top oralig‘i eski ball nisbatiga zid resurslar mavjud: '
                    .implode(', ', $analysis['conflicts']),
                );
            }

            foreach ($analysis['rows'] as $row) {
                /** @var Datum $datum */
                $datum = $row['datum'];
                $datum->update([
                    'point' => $row['point'],
                    'university_tier' => $row['university_tier'],
                    'reason' => $row['reason'],
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '2.1.5 balli eski '.$row['old_percentage'].'% o‘rniga '
                        .$row['new_percentage'].'% qoidasida matematik qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($row['old_point'], 4, '.', '').'. '
                        .'Yangi ball: '.number_format($row['point'], 4, '.', '').'.',
                    'message_type' => 'criterion_2_1_5_point_recalculated',
                ]);
            }

            unset($analysis['rows']);

            return $analysis;
        }, 3);

        if ($result['changes'] > 0) {
            $this->recalculateReportPoints->handle($report);
        }

        return $result;
    }

    /**
     * @return array{total: int, changes: int, conflicts: array<int, int>, sources: array<string, int>, rows: Collection<int, array{datum: Datum, point: float, old_point: float, university_tier: string, reason: string, old_percentage: int, new_percentage: int}>}
     */
    private function analyse(Report $report, bool $lockForUpdate = false): array
    {
        $criterion = $this->criterion($report, $lockForUpdate);
        $maximumPoints = $criterion->criterionEvaluations
            ->where('has', '1')
            ->mapWithKeys(fn ($evaluation): array => [
                $evaluation->evaluation => (float) $evaluation->score,
            ]);
        $data = Datum::query()
            ->where('criterion_id', $criterion->getKey())
            ->where('status', 'accepted')
            ->with('user:id,degree')
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->orderBy('id')
            ->get();
        $rows = collect();
        $conflicts = [];
        $sources = [];

        foreach ($data as $datum) {
            $maximumPoint = $datum->user === null
                ? null
                : $maximumPoints->get($datum->user->degree);
            $correction = is_numeric($maximumPoint)
                ? $this->correctionForOldPoint($datum->point, (float) $maximumPoint)
                : null;

            if ($correction === null) {
                continue;
            }

            if ($datum->university_tier !== null
                && $datum->university_tier !== $correction['university_tier']) {
                $conflicts[] = $datum->getKey();

                continue;
            }

            $calculated = ProfessionalDevelopmentCriterionRule::apply(
                new AiEvaluationResult(
                    status: 'accepted',
                    point: $datum->point,
                    reason: $datum->reason,
                    universityTier: $correction['university_tier'],
                ),
                (float) $maximumPoint,
            );

            if ($calculated->status !== 'accepted') {
                $conflicts[] = $datum->getKey();

                continue;
            }

            $sources[$correction['source']] = ($sources[$correction['source']] ?? 0) + 1;
            $rows->push([
                'datum' => $datum,
                'point' => $calculated->point,
                'old_point' => $datum->point,
                'university_tier' => $calculated->universityTier,
                'reason' => $calculated->reason,
                'old_percentage' => $correction['old_percentage'],
                'new_percentage' => $correction['new_percentage'],
            ]);
        }

        return [
            'total' => $data->count(),
            'changes' => $rows->count(),
            'conflicts' => $conflicts,
            'sources' => $sources,
            'rows' => $rows,
        ];
    }

    private function criterion(Report $report, bool $lockForUpdate): Criterion
    {
        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', ProfessionalDevelopmentCriterionRule::CODE)
            ->with('criterionEvaluations:id,criterion_id,evaluation,has,score')
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->first();

        if ($criterion === null) {
            throw new RuntimeException('Tanlangan hisobotda 2.1.5 kriteriyasi topilmadi.');
        }

        return $criterion;
    }

    /**
     * @return array{university_tier: string, old_percentage: int, new_percentage: int, source: string}|null
     */
    private function correctionForOldPoint(float $point, float $maximumPoint): ?array
    {
        if (abs($point - round($maximumPoint * 0.7, 4)) < 0.00005) {
            return [
                'university_tier' => 'top_300',
                'old_percentage' => 70,
                'new_percentage' => 75,
                'source' => 'old_70_percent',
            ];
        }

        if (abs($point - round($maximumPoint * 0.2, 4)) < 0.00005) {
            return [
                'university_tier' => 'top_1000',
                'old_percentage' => 20,
                'new_percentage' => 25,
                'source' => 'old_20_percent',
            ];
        }

        return null;
    }
}
