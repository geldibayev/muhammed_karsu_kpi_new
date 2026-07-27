<?php

namespace App\Actions;

use App\Enums\RatingMode;
use App\Models\Department;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetRatingIndexData
{
    public function __construct(
        private PaginateRatingUsers $paginateRatingUsers,
        private GetRatingUnitRankings $getRatingUnitRankings,
    ) {}

    /**
     * @param  array{search?: string|null, mode?: string, degree_group?: string, faculty?: int|null, department?: int|null}  $filters
     * @return array{departments: Collection<int, Department>, faculties: Collection<int, Department>, filters: array<string, mixed>, mode: RatingMode, report: Report|null, unitRankings: LengthAwarePaginator|null, users: LengthAwarePaginator|null}
     */
    public function handle(array $filters): array
    {
        $mode = RatingMode::fromFilters($filters);
        $filters['mode'] = $mode->value;
        $filters['degree_group'] = in_array($mode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true)
            ? $mode->value
            : null;

        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $faculties = Department::query()
            ->select(['id', 'name'])
            ->whereNull('parent_id')
            ->orderBy('name->uz')
            ->get();

        $departments = Department::query()
            ->select(['id', 'name', 'parent_id'])
            ->whereNotNull('parent_id')
            ->when(
                $filters['faculty'] ?? null,
                fn (Builder $query, int $facultyId): Builder => $query->where('parent_id', $facultyId),
            )
            ->orderBy('name->uz')
            ->get();

        $showUnitRankings = ($mode === RatingMode::Faculties && empty($filters['faculty']))
            || ($mode === RatingMode::Departments && empty($filters['department']));

        return [
            'departments' => $departments,
            'faculties' => $faculties,
            'filters' => $filters,
            'mode' => $mode,
            'report' => $report,
            'unitRankings' => $showUnitRankings
                ? $this->getRatingUnitRankings->handle($report, $filters)
                : null,
            'users' => $showUnitRankings
                ? null
                : $this->paginateRatingUsers->handle($report, $filters),
        ];
    }
}
