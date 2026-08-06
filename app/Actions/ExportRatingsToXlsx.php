<?php

namespace App\Actions;

use App\Enums\RatingMode;
use App\Models\Report;
use App\Models\User;
use App\Support\XlsxWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRatingsToXlsx
{
    public function __construct(
        private PaginateRatingUsers $paginateRatingUsers,
        private GetRatingUnitRankings $getRatingUnitRankings,
        private XlsxWriter $xlsxWriter,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function handle(array $filters): BinaryFileResponse
    {
        $mode = RatingMode::fromFilters($filters);
        $filters['mode'] = $mode->value;
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);
        $unitOverview = ($mode === RatingMode::Faculties && empty($filters['faculty']))
            || ($mode === RatingMode::Departments && empty($filters['department']));

        [$headings, $rows] = match (true) {
            $unitOverview => $this->unitRows($mode, $this->getRatingUnitRankings->all($report, $filters)),
            $mode === RatingMode::AllUsers => $this->allUserRows(
                $this->paginateRatingUsers->all($report, $filters),
            ),
            default => $this->userRows($this->paginateRatingUsers->all($report, $filters)),
        };

        $path = $this->xlsxWriter->write($mode->label(), $headings, $rows);
        $filename = 'karsu-reyting-'.$mode->value.'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, array<string, int|float|string>>  $units
     * @return array{0: array<int, string>, 1: array<int, array<int, float|int|string>>}
     */
    private function unitRows(RatingMode $mode, Collection $units): array
    {
        $headings = $mode === RatingMode::Faculties
            ? ['O‘rin', 'Fakultet', 'Xodimlar', 'Ilmiy darajali', 'Darajasiz', 'Jami ball', 'O‘rtacha ball']
            : ['O‘rin', 'Fakultet', 'Kafedra', 'Xodimlar', 'Ilmiy darajali', 'Darajasiz', 'Jami ball', 'O‘rtacha ball'];

        $rows = $units->values()->map(function (array $unit, int $index) use ($mode): array {
            $common = [
                $index + 1,
                $unit['name'],
                $unit['users_count'],
                $unit['with_degree_count'],
                $unit['without_degree_count'],
                (float) $unit['total_points'],
                (float) $unit['average_points'],
            ];

            if ($mode === RatingMode::Faculties) {
                return $common;
            }

            return [
                $common[0],
                $unit['faculty_name'],
                ...array_slice($common, 1),
            ];
        })->all();

        return [$headings, $rows];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{0: array<int, string>, 1: array<int, array<int, float|int|string>>}
     */
    private function userRows(Collection $users): array
    {
        $headings = [
            'O‘rin',
            'HEMIS ID',
            'F.I.Sh.',
            'Fakultet',
            'Kafedra',
            'Reytingdagi lavozimi',
            'Ilmiy daraja guruhi',
            'Jami ball',
        ];
        $rows = $users->values()->map(function (User $user, int $index): array {
            $workplace = $user->ratingWorkplace;
            $department = $workplace?->department;
            $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);

            return [
                $index + 1,
                (string) $user->hemis_id,
                $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi'),
                (string) data_get($faculty?->name, 'uz', '—'),
                $department?->parent_id !== null
                    ? (string) data_get($department->name, 'uz', '—')
                    : '—',
                $workplace?->position?->name ?? '—',
                $user->degree === 'hold_degrees' ? 'Ilmiy darajaga ega' : 'Ilmiy darajaga ega emas',
                (float) ($user->total_points ?? 0),
            ];
        })->all();

        return [$headings, $rows];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{0: array<int, string>, 1: array<int, array<int, int|string>>}
     */
    private function allUserRows(Collection $users): array
    {
        $headings = [
            'T/r',
            'HEMIS ID',
            'F.I.Sh.',
            'Fakultet',
            'Kafedra',
            'Reytingdagi lavozimi',
            'Holati',
            'Yuklagan resurslari soni',
        ];
        $rows = $users->values()->map(function (User $user, int $index): array {
            $workplace = $user->ratingWorkplace;
            $department = $workplace?->department;
            $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);
            $resourceCount = (int) ($user->uploaded_resources_count ?? 0);

            return [
                $index + 1,
                (string) $user->hemis_id,
                $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi'),
                (string) data_get($faculty?->name, 'uz', '—'),
                $department?->parent_id !== null
                    ? (string) data_get($department->name, 'uz', '—')
                    : '—',
                $workplace?->position?->name ?? '—',
                $resourceCount > 0 ? 'Resurs yuklagan' : 'Resurs yuklamagan',
                $resourceCount,
            ];
        })->all();

        return [$headings, $rows];
    }
}
