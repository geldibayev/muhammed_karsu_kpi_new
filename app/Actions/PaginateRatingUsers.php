<?php

namespace App\Actions;

use App\Enums\RatingMode;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaginateRatingUsers
{
    /**
     * @param  array{search?: string|null, degree_group?: string, faculty?: int|null, department?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?Report $report, array $filters): LengthAwarePaginator
    {
        return $this->query($report, $filters)
            ->paginate(25)
            ->withQueryString();
    }

    /** @return Collection<int, User> */
    public function all(?Report $report, array $filters): Collection
    {
        return $this->query($report, $filters)->get();
    }

    /**
     * @param  array{search?: string|null, mode?: string, degree_group?: string, faculty?: int|null, department?: int|null}  $filters
     * @return Builder<User>
     */
    private function query(?Report $report, array $filters): Builder
    {
        $mode = RatingMode::fromFilters($filters);

        return User::query()
            ->select(['id', 'hemis_id', 'name', 'image', 'degree'])
            ->whereHas('ratingWorkplace')
            ->has('primaryWorkplaces', '<', 2)
            ->with([
                'ratingWorkplace' => fn (HasOne $query): HasOne => $query->select([
                    'workplaces.id',
                    'workplaces.user_id',
                    'workplaces.department_id',
                    'workplaces.staff_position_id',
                ]),
                'ratingWorkplace.position:id,name',
                'ratingWorkplace.department:id,name,parent_id',
                'ratingWorkplace.department.parent:id,name',
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
                $mode === RatingMode::WithDegree,
                fn (Builder $query): Builder => $query->where('degree', 'hold_degrees'),
            )
            ->when(
                $mode === RatingMode::WithoutDegree,
                fn (Builder $query): Builder => $query->where('degree', '!=', 'hold_degrees'),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query
                    ->tap(fn (Builder $searchQuery): Builder => $this->applyNameSearch($searchQuery, $search)),
            )
            ->when(
                $filters['faculty'] ?? null,
                fn (Builder $query, int $facultyId): Builder => $query
                    ->whereHas('ratingWorkplace.department', fn (Builder $departmentQuery): Builder => $departmentQuery
                        ->where(fn (Builder $facultyQuery): Builder => $facultyQuery
                            ->whereKey($facultyId)
                            ->orWhere('parent_id', $facultyId))),
            )
            ->when(
                $filters['department'] ?? null,
                fn (Builder $query, int $departmentId): Builder => $query
                    ->whereHas('ratingWorkplace', fn (Builder $workplaceQuery): Builder => $workplaceQuery
                        ->where('department_id', $departmentId)),
            )
            ->orderByDesc('total_points')
            ->orderBy('id');
    }

    private function applyNameSearch(Builder $query, string $search): Builder
    {
        $terms = preg_split('/\s+/u', trim($search), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $grammar = $query->getQuery()->getGrammar();
        $nameColumns = ['name->full', 'name->first', 'name->last', 'name->third', 'name->short'];

        foreach ($terms as $term) {
            $query->where(function (Builder $nameQuery) use ($grammar, $nameColumns, $term): void {
                foreach ($nameColumns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';

                    $nameQuery->{$method}(
                        'LOWER('.$grammar->wrap($column).') LIKE ?',
                        ['%'.Str::lower($term).'%'],
                    );
                }
            });
        }

        return $query;
    }
}
