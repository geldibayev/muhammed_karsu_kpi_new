<?php

namespace App\Actions;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginateRatingUsers
{
    /**
     * @param  array{search?: string|null, degree_group?: string, faculty?: int|null, department?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?Report $report, array $filters): LengthAwarePaginator
    {
        $degreeGroup = $filters['degree_group'] ?? 'with_degree';

        return User::query()
            ->select(['id', 'name', 'image', 'degree'])
            ->with([
                'primaryWorkplace' => fn (HasOne $query): HasOne => $query->select([
                    'workplaces.id',
                    'workplaces.user_id',
                    'workplaces.department_id',
                    'workplaces.staff_position_id',
                ]),
                'primaryWorkplace.position:id,name',
                'primaryWorkplace.department:id,name,parent_id',
                'primaryWorkplace.department.parent:id,name',
            ])
            ->withSum([
                'points as total_points' => function (Builder $query) use ($report): void {
                    $query->when(
                        $report !== null,
                        fn (Builder $query): Builder => $query->where('report_id', $report->getKey()),
                        fn (Builder $query): Builder => $query->whereNull('report_id'),
                    );
                },
            ], 'point')
            ->when(
                $degreeGroup === 'with_degree',
                fn (Builder $query): Builder => $query->where('degree', 'hold_degrees'),
                fn (Builder $query): Builder => $query->where('degree', '!=', 'hold_degrees'),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query
                    ->where('name->full', 'like', "%{$search}%"),
            )
            ->when(
                $filters['faculty'] ?? null,
                fn (Builder $query, int $facultyId): Builder => $query
                    ->whereHas('primaryWorkplace.department', fn (Builder $departmentQuery): Builder => $departmentQuery
                        ->where(fn (Builder $facultyQuery): Builder => $facultyQuery
                            ->whereKey($facultyId)
                            ->orWhere('parent_id', $facultyId))),
            )
            ->when(
                $filters['department'] ?? null,
                fn (Builder $query, int $departmentId): Builder => $query
                    ->whereHas('primaryWorkplace', fn (Builder $workplaceQuery): Builder => $workplaceQuery
                        ->where('department_id', $departmentId)),
            )
            ->orderByDesc('total_points')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
    }
}
