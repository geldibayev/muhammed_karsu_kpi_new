<?php

namespace App\Actions;

use App\Enums\RatingMode;
use App\Models\Department;
use App\Models\Report;
use App\Models\StaffPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GetRatingIndexData
{
    private const EXCLUDED_POSITION_NAME_FRAGMENTS = [
        'kabinetmudiri',
        'matbuotkotibi',
        'bolimboshligi',
        'menejer',
        'muhandis',
        'tyutor',
        'uslubchi',
        'vebdizayner',
        'webdizayner',
        'yoshlarbilanishlash',
        'dekanmuovini',
        'dekanorinbosari',
    ];

    public function __construct(
        private PaginateRatingUsers $paginateRatingUsers,
        private GetRatingUnitRankings $getRatingUnitRankings,
    ) {}

    /**
     * @param  array{search?: string|null, mode?: string, degree_group?: string, resource_status?: string|null, position?: int|null, faculty?: int|null, department?: int|null}  $filters
     * @return array{departments: Collection<int, Department>, faculties: Collection<int, Department>, positions: Collection<int, StaffPosition>, filters: array<string, mixed>, mode: RatingMode, report: Report|null, unitRankings: LengthAwarePaginator|null, users: LengthAwarePaginator|null}
     */
    public function handle(array $filters): array
    {
        $mode = RatingMode::fromFilters($filters);
        $filters['mode'] = $mode->value;
        $filters['degree_group'] = in_array($mode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true)
            ? $mode->value
            : null;
        $filters['position'] = in_array($mode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true)
            ? ($filters['position'] ?? null)
            : null;

        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $faculties = Department::query()
            ->select(['id', 'name'])
            ->faculties()
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

        $positions = StaffPosition::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->reject(function (StaffPosition $position): bool {
                $normalizedName = Str::lower(
                    preg_replace('/[^\p{L}\p{N}]+/u', '', $position->name) ?? '',
                );

                return $normalizedName === 'rektor'
                    || Str::contains($normalizedName, self::EXCLUDED_POSITION_NAME_FRAGMENTS);
            })
            ->values();

        $showUnitRankings = ($mode === RatingMode::Faculties && empty($filters['faculty']))
            || ($mode === RatingMode::Departments && empty($filters['department']));

        return [
            'departments' => $departments,
            'faculties' => $faculties,
            'positions' => $positions,
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
