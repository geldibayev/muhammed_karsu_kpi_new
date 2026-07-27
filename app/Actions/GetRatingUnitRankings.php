<?php

namespace App\Actions;

use App\Enums\RatingMode;
use App\Models\Report;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GetRatingUnitRankings
{
    public function __construct(private PaginateRatingUsers $paginateRatingUsers) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, int|float|string>>
     */
    public function handle(?Report $report, array $filters): LengthAwarePaginator
    {
        $rows = $this->all($report, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, int|float|string>>
     */
    public function all(?Report $report, array $filters): Collection
    {
        $mode = RatingMode::fromFilters($filters);
        $queryFilters = [
            ...$filters,
            'mode' => $mode->value,
            'search' => null,
            'department' => null,
            'faculty' => $mode === RatingMode::Departments ? ($filters['faculty'] ?? null) : null,
        ];
        $users = $this->paginateRatingUsers->all($report, $queryFilters);
        $rows = $mode === RatingMode::Faculties
            ? $this->facultyRows($users)
            : $this->departmentRows($users);
        $search = Str::lower((string) ($filters['search'] ?? ''));

        return $rows
            ->when(
                $search !== '',
                fn (Collection $rows): Collection => $rows->filter(
                    fn (array $row): bool => Str::contains(Str::lower($row['name']), $search),
                ),
            )
            ->sort(function (array $first, array $second): int {
                $pointsComparison = $second['total_points'] <=> $first['total_points'];

                if ($pointsComparison !== 0) {
                    return $pointsComparison;
                }

                return [$first['name'], $first['id']] <=> [$second['name'], $second['id']];
            })
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, int|float|string>>
     */
    private function facultyRows(Collection $users): Collection
    {
        return $users
            ->mapToGroups(function (User $user): array {
                $department = $user->ratingWorkplace?->department;
                $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);

                return $faculty === null ? [] : [$faculty->getKey() => [$user, $faculty]];
            })
            ->map(fn (Collection $items, int|string $facultyId): array => $this->row(
                (int) $facultyId,
                (string) data_get($items->first()[1]->name, 'uz', 'Nomsiz fakultet'),
                $items->pluck(0),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, int|float|string>>
     */
    private function departmentRows(Collection $users): Collection
    {
        return $users
            ->filter(fn (User $user): bool => $user->ratingWorkplace?->department?->parent_id !== null)
            ->mapToGroups(function (User $user): array {
                $department = $user->ratingWorkplace->department;

                return [$department->getKey() => [$user, $department]];
            })
            ->map(function (Collection $items, int|string $departmentId): array {
                $department = $items->first()[1];

                return [
                    ...$this->row(
                        (int) $departmentId,
                        (string) data_get($department->name, 'uz', 'Nomsiz kafedra'),
                        $items->pluck(0),
                    ),
                    'faculty_id' => (int) $department->parent_id,
                    'faculty_name' => (string) data_get($department->parent?->name, 'uz', 'Nomsiz fakultet'),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<string, int|float|string>
     */
    private function row(int $id, string $name, Collection $users): array
    {
        $totalPoints = (float) $users->sum(fn (User $user): float => (float) ($user->total_points ?? 0));
        $usersCount = $users->count();

        return [
            'id' => $id,
            'name' => $name,
            'users_count' => $usersCount,
            'with_degree_count' => $users->where('degree', 'hold_degrees')->count(),
            'without_degree_count' => $users->where('degree', '!=', 'hold_degrees')->count(),
            'total_points' => round($totalPoints, 4),
            'average_points' => $usersCount > 0 ? round($totalPoints / $usersCount, 4) : 0,
        ];
    }
}
