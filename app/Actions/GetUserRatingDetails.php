<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GetUserRatingDetails
{
    /**
     * @return array{
     *     report: Report|null,
     *     user: User,
     *     criterionSections: Collection<int, array{criterion: Criterion, number: int, rows: Collection<int, array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, evaluators: Collection<int, array{type: string, name: string}>}>}>,
     *     totalPoints: float
     * }
     */
    public function handle(User $user): array
    {
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $user->load([
            'primaryWorkplace.position',
            'primaryWorkplace.department.parent',
        ]);

        $points = $this->points($user, $report);
        $historiesByCriterion = $this->historiesByCriterion($user, $report, $points);
        $submissionsByCriterion = $this->submissionsByCriterion($user, $report);

        return [
            'report' => $report,
            'user' => $user,
            'criterionSections' => $this->criterionSections(
                $report,
                $points->keyBy('criterion_id'),
                $historiesByCriterion,
                $submissionsByCriterion,
            ),
            'totalPoints' => (float) $points->sum('point'),
        ];
    }

    /**
     * @param  Collection<int, Point>  $pointsByCriterion
     * @param  Collection<int, Collection<int, DatumHistory>>  $historiesByCriterion
     * @param  Collection<int, Collection<int, Datum>>  $submissionsByCriterion
     * @return Collection<int, array{criterion: Criterion, number: int, rows: Collection<int, array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, evaluators: Collection<int, array{type: string, name: string}>}>}>
     */
    private function criterionSections(
        ?Report $report,
        Collection $pointsByCriterion,
        Collection $historiesByCriterion,
        Collection $submissionsByCriterion,
    ): Collection {
        if ($report === null) {
            return collect();
        }

        return Criterion::query()
            ->select(['id', 'name', 'report_id'])
            ->whereBelongsTo($report)
            ->whereNull('parent_id')
            ->where('status', '1')
            ->with([
                'children' => fn (HasMany $query): HasMany => $query
                    ->select(['id', 'name', 'parent_id', 'checking', 'ai_model'])
                    ->where('status', '1')
                    ->orderBy('id'),
            ])
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (Criterion $parent, int $index) use (
                $pointsByCriterion,
                $historiesByCriterion,
                $submissionsByCriterion,
            ): array {
                $sectionNumber = $index + 1;

                return [
                    'criterion' => $parent,
                    'number' => $sectionNumber,
                    'rows' => $parent->children->map(fn (Criterion $criterion): array => $this->criterionRow(
                        $criterion,
                        $sectionNumber,
                        $pointsByCriterion->get($criterion->getKey()),
                        $historiesByCriterion->get($criterion->getKey(), collect()),
                        $submissionsByCriterion->get($criterion->getKey(), collect()),
                    )),
                ];
            });
    }

    /**
     * @param  Collection<int, DatumHistory>  $histories
     * @param  Collection<int, Datum>  $submissions
     * @return array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, evaluators: Collection<int, array{type: string, name: string}>}
     */
    private function criterionRow(
        Criterion $criterion,
        int $sectionNumber,
        ?Point $point,
        Collection $histories,
        Collection $submissions,
    ): array {
        $pendingCount = $submissions
            ->whereIn('status', [DatumStatus::Received->value, DatumStatus::Checking->value])
            ->count();

        if ($point !== null) {
            $evaluators = $this->evaluators($criterion, $histories);

            if ($pendingCount > 0) {
                $evaluators->push(['type' => 'pending', 'name' => 'Baholash kutilmoqda']);
            }

            return $this->row($criterion, $sectionNumber, 'scored', $point->point, $pendingCount, $evaluators);
        }

        if ($pendingCount > 0) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'pending',
                null,
                $pendingCount,
                collect([['type' => 'pending', 'name' => 'Baholash kutilmoqda']]),
            );
        }

        if ($submissions->contains('status', DatumStatus::Accepted->value)) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'accepted',
                null,
                0,
                collect([['type' => 'status', 'name' => 'Yakuniy ball hisoblanmoqda']]),
            );
        }

        if ($submissions->contains('status', DatumStatus::Cancelled->value)) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'cancelled',
                null,
                0,
                collect([['type' => 'status', 'name' => 'Ma’lumot qaytarilgan']]),
            );
        }

        return $this->row(
            $criterion,
            $sectionNumber,
            'unuploaded',
            null,
            0,
            collect([['type' => 'unuploaded', 'name' => 'Ma’lumot yuklanmagan']]),
        );
    }

    /**
     * @param  Collection<int, array{type: string, name: string}>  $evaluators
     * @return array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, evaluators: Collection<int, array{type: string, name: string}>}
     */
    private function row(
        Criterion $criterion,
        int $sectionNumber,
        string $state,
        ?float $point,
        int $pendingCount,
        Collection $evaluators,
    ): array {
        return [
            'criterion' => $criterion,
            'code' => "{$sectionNumber}/{$criterion->getKey()}",
            'state' => $state,
            'point' => $point,
            'pending_count' => $pendingCount,
            'evaluators' => $evaluators,
        ];
    }

    /** @return Collection<int, Point> */
    private function points(User $user, ?Report $report): Collection
    {
        if ($report === null) {
            return collect();
        }

        return Point::query()
            ->select(['id', 'user_id', 'criterion_id', 'report_id', 'point'])
            ->whereBelongsTo($user)
            ->whereBelongsTo($report)
            ->orderBy('criterion_id')
            ->get();
    }

    /** @return Collection<int, Collection<int, Datum>> */
    private function submissionsByCriterion(User $user, ?Report $report): Collection
    {
        if ($report === null) {
            return collect();
        }

        return Datum::query()
            ->select(['id', 'user_id', 'criterion_id', 'status'])
            ->whereBelongsTo($user)
            ->where('status', '!=', 'deleted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query->whereBelongsTo($report))
            ->get()
            ->groupBy('criterion_id');
    }

    /**
     * @param  Collection<int, Point>  $points
     * @return Collection<int, Collection<int, DatumHistory>>
     */
    private function historiesByCriterion(User $user, ?Report $report, Collection $points): Collection
    {
        if ($report === null || $points->isEmpty()) {
            return collect();
        }

        return DatumHistory::query()
            ->select(['id', 'datum_id', 'user_id', 'type', 'message_type'])
            ->where(function (Builder $query): void {
                $query->where('message_type', 'manual_review_approved')
                    ->orWhere(function (Builder $query): void {
                        $query->where('message_type', 'ai_evaluation')
                            ->where('type', 'success');
                    });
            })
            ->whereHas('datum', fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->where('status', 'accepted')
                ->whereIn('criterion_id', $points->pluck('criterion_id'))
                ->whereHas('criterion', fn (Builder $query): Builder => $query->whereBelongsTo($report)))
            ->with(['datum:id,criterion_id', 'user:id,name'])
            ->get()
            ->groupBy(fn (DatumHistory $history): int => $history->datum->criterion_id);
    }

    /**
     * @param  Collection<int, DatumHistory>  $histories
     * @return Collection<int, array{type: string, name: string}>
     */
    private function evaluators(Criterion $criterion, Collection $histories): Collection
    {
        $evaluators = $histories->map(function (DatumHistory $history) use ($criterion): array {
            if ($history->message_type === 'ai_evaluation') {
                return $this->aiEvaluator($criterion);
            }

            return [
                'type' => 'manual',
                'name' => $history->user?->full ?: ($history->user?->short ?: 'Noma’lum baholovchi'),
            ];
        })->unique(fn (array $evaluator): string => $evaluator['type'].'|'.$evaluator['name'])->values();

        if ($evaluators->isNotEmpty()) {
            return $evaluators;
        }

        if ($criterion->checking === 'ai') {
            return collect([$this->aiEvaluator($criterion)]);
        }

        return collect([['type' => 'unknown', 'name' => 'Auditda qayd etilmagan']]);
    }

    /** @return array{type: string, name: string} */
    private function aiEvaluator(Criterion $criterion): array
    {
        $model = $criterion->ai_model ? " ({$criterion->ai_model})" : '';

        return [
            'type' => 'ai',
            'name' => 'Sun’iy intellekt tomonidan baholangan'.$model,
        ];
    }
}
