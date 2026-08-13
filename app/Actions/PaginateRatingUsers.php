<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Enums\RatingMode;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaginateRatingUsers
{
    /**
     * @param  array{search?: string|null, degree_group?: string, resource_status?: string|null, position?: int|null, faculty?: int|null, department?: int|null}  $filters
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
     * @param  array{search?: string|null, mode?: string, degree_group?: string, resource_status?: string|null, position?: int|null, faculty?: int|null, department?: int|null}  $filters
     * @return Builder<User>
     */
    private function query(?Report $report, array $filters): Builder
    {
        $mode = RatingMode::fromFilters($filters);
        $positionIds = in_array($mode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true)
            && ($filters['position'] ?? null)
                ? $this->positionIds((int) $filters['position'])
                : [];

        return User::query()
            ->select(['id', 'hemis_id', 'name', 'image', 'degree'])
            ->active()
            ->academicRatingParticipants()
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
            ->withCount([
                'submissions as uploaded_resources_count' => fn (Builder $query): Builder => $this
                    ->applySubmissionReportScope($query, $report),
            ])
            ->when(
                $mode === RatingMode::AllUsers,
                fn (Builder $query): Builder => $query->withCount([
                    'submissions as accepted_resources_count' => fn (Builder $query): Builder => $this
                        ->applySubmissionReportScope($query, $report)
                        ->where('status', DatumStatus::Accepted->value),
                    'submissions as cancelled_resources_count' => fn (Builder $query): Builder => $this
                        ->applySubmissionReportScope($query, $report)
                        ->where('status', DatumStatus::Cancelled->value),
                    'submissions as reviewing_resources_count' => fn (Builder $query): Builder => $this
                        ->applySubmissionReportScope($query, $report)
                        ->whereIn('status', [
                            DatumStatus::Received->value,
                            DatumStatus::Checking->value,
                        ]),
                ]),
            )
            ->withSum([
                'points as total_points' => function (Builder $query) use ($report): void {
                    $query->when(
                        $report !== null,
                        fn (Builder $query): Builder => $query->forRatingReport($report),
                        fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
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
                $positionIds,
                fn (Builder $query, array $positionIds): Builder => $query
                    ->whereHas('ratingWorkplace', fn (Builder $workplaceQuery): Builder => $workplaceQuery
                        ->whereIn('staff_position_id', $positionIds)),
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
            ->when(
                $mode === RatingMode::AllUsers && ($filters['resource_status'] ?? null) === 'uploaded',
                fn (Builder $query): Builder => $query->whereHas(
                    'submissions',
                    fn (Builder $query): Builder => $this->applySubmissionReportScope($query, $report),
                ),
            )
            ->when(
                $mode === RatingMode::AllUsers && ($filters['resource_status'] ?? null) === 'not_uploaded',
                fn (Builder $query): Builder => $query->whereDoesntHave(
                    'submissions',
                    fn (Builder $query): Builder => $this->applySubmissionReportScope($query, $report),
                ),
            )
            ->when(
                $mode === RatingMode::AllUsers,
                fn (Builder $query): Builder => $query
                    ->orderByDesc('uploaded_resources_count')
                    ->orderBy('name->full')
                    ->orderBy('id'),
                fn (Builder $query): Builder => $query
                    ->orderByDesc('total_points')
                    ->orderBy('id'),
            );
    }

    /** @return list<int> */
    private function positionIds(int $positionId): array
    {
        $position = StaffPosition::query()->find($positionId, ['id', 'name']);

        if ($position === null || ! in_array($this->normalizePositionName($position->name), ['dekanmuovini', 'dekanmuavini'], true)) {
            return [$positionId];
        }

        return StaffPosition::query()
            ->get(['id', 'name'])
            ->filter(function (StaffPosition $position): bool {
                $name = $this->normalizePositionName($position->name);

                return in_array($name, ['dekanmuovini', 'dekanmuavini'], true)
                    || (str_contains($name, 'yoshlarbilanishlash') && str_contains($name, 'dekanorinbosari'));
            })
            ->pluck('id')
            ->all();
    }

    private function applySubmissionReportScope(Builder $query, ?Report $report): Builder
    {
        return $query
            ->where('status', '!=', 'deleted')
            ->when(
                $report !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'criterion',
                    fn (Builder $query): Builder => $query->whereBelongsTo($report),
                ),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
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

    private function normalizePositionName(string $name): string
    {
        return Str::lower(preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? '');
    }
}
