<?php

namespace App\Actions;

use App\Models\Department;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetRatingIndexData
{
    public function __construct(private PaginateRatingUsers $paginateRatingUsers) {}

    /**
     * @param  array{search?: string|null, degree_group?: string, faculty?: int|null, department?: int|null}  $filters
     * @return array{departments: Collection<int, Department>, faculties: Collection<int, Department>, filters: array<string, mixed>, report: Report|null, users: LengthAwarePaginator<int, User>}
     */
    public function handle(array $filters): array
    {
        $filters['degree_group'] ??= 'with_degree';

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

        return [
            'departments' => $departments,
            'faculties' => $faculties,
            'filters' => $filters,
            'report' => $report,
            'users' => $this->paginateRatingUsers->handle($report, $filters),
        ];
    }
}
