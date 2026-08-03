<?php

namespace App\Actions;

use App\Models\EmploymentForm;
use App\Models\User;
use App\Models\Workplace;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportExternalPartTimeUsersToXlsx
{
    public function __construct(private XlsxWriter $xlsxWriter) {}

    public function handle(): BinaryFileResponse
    {
        $users = User::query()
            ->select(['id', 'hemis_id', 'name'])
            ->whereHas(
                'workplaces',
                fn (Builder $query): Builder => $query
                    ->where('form_id', EmploymentForm::EXTERNAL_PART_TIME_ID),
            )
            ->with([
                'workplaces' => fn (HasMany $query): HasMany => $query
                    ->where('form_id', EmploymentForm::EXTERNAL_PART_TIME_ID)
                    ->select(['id', 'user_id', 'department_id', 'staff_position_id', 'form_id'])
                    ->with([
                        'department:id,name,parent_id',
                        'department.parent:id,name,parent_id',
                        'position:id,name',
                        'form:id,name',
                    ]),
            ])
            ->orderBy('name->full')
            ->get();

        $rows = $users->map(function (User $user, int $index): array {
            $faculties = $user->workplaces
                ->map(function (Workplace $workplace): string {
                    $department = $workplace->department;
                    $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);

                    return (string) data_get($faculty?->name, 'uz', '—');
                })
                ->unique()
                ->implode(', ');
            $departments = $user->workplaces
                ->map(fn (Workplace $workplace): string => $workplace->department?->parent_id !== null
                    ? (string) data_get($workplace->department->name, 'uz', '—')
                    : '—')
                ->unique()
                ->implode(', ');
            $positions = $user->workplaces
                ->pluck('position.name')
                ->filter()
                ->unique()
                ->implode(', ');
            $forms = $user->workplaces
                ->pluck('form.name')
                ->filter()
                ->unique()
                ->implode(', ');

            return [
                $index + 1,
                (string) $user->hemis_id,
                $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi'),
                $faculties ?: '—',
                $departments ?: '—',
                $positions ?: '—',
                $forms ?: 'O‘rindoshlik (tashqi)',
            ];
        });

        $path = $this->xlsxWriter->write('Tashqi o‘rindoshlar', [
            'T/r',
            'HEMIS ID',
            'F.I.Sh.',
            'Fakultet',
            'Kafedra',
            'Lavozim',
            'Mehnat shakli',
        ], $rows);

        return response()
            ->download(
                $path,
                'tashqi-orindoshlar-'.now()->format('Y-m-d-His').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }
}
