<?php

namespace App\Actions;

use App\Models\EmploymentForm;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GetExternalPartTimeUsers
{
    /** @return LengthAwarePaginator<int, array<string, int|string>> */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->query()
            ->paginate($perPage)
            ->through(fn (User $user): array => $this->row($user));
    }

    /** @return Collection<int, array<string, int|string>> */
    public function all(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (User $user): array => $this->row($user));
    }

    /** @return Builder<User> */
    private function query(): Builder
    {
        return User::query()
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
            ->orderBy('name->full');
    }

    /** @return array{id: int, hemis_id: int|string, name: string, faculties: string, departments: string, positions: string, forms: string} */
    private function row(User $user): array
    {
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
        $positions = $user->workplaces->pluck('position.name')->filter()->unique()->implode(', ');
        $forms = $user->workplaces->pluck('form.name')->filter()->unique()->implode(', ');

        return [
            'id' => $user->getKey(),
            'hemis_id' => $user->hemis_id ?? '—',
            'name' => $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi'),
            'faculties' => $faculties ?: '—',
            'departments' => $departments ?: '—',
            'positions' => $positions ?: '—',
            'forms' => $forms ?: 'O‘rindoshlik (tashqi)',
        ];
    }
}
