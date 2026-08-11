<?php

namespace App\Actions;

use App\Data\HemisWorkplaceSyncResult;
use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class SyncHemisWorkplaces
{
    public const MISSING_WORKPLACE_MESSAGE = 'HEMIS foydalanuvchi uchun ish joyi ma’lumotini qaytarmadi.';

    public function __construct(
        private ResolveUserEvaluationCategory $resolveUserEvaluationCategory,
    ) {}

    public function handle(User $user): HemisWorkplaceSyncResult
    {
        return Cache::lock("hemis:workplaces:sync:{$user->getKey()}", 60)
            ->block(15, fn (): HemisWorkplaceSyncResult => $this->sync($user));
    }

    private function sync(User $user): HemisWorkplaceSyncResult
    {
        $response = Http::acceptJson()
            ->withToken((string) config('services.hemis.api_key'))
            ->connectTimeout(5)
            ->timeout(10)
            ->retry([200, 500])
            ->get((string) config('services.hemis.employee_api_url'), [
                'type' => 'all',
                'search' => $user->hemis_id,
            ])
            ->throw();

        $employees = data_get($response->json(), 'data.items');

        if (! is_array($employees)) {
            throw new UnexpectedValueException('HEMIS ish joylari ro‘yxatini yaroqli formatda qaytarmadi.');
        }

        if ($employees === []) {
            throw new UnexpectedValueException(self::MISSING_WORKPLACE_MESSAGE);
        }

        [$degreeChanged, $primaryWorkplaceCount] = DB::transaction(function () use ($user, $employees): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $workplaces = collect($employees)
                ->filter(fn (mixed $employee): bool => $this->isWorkingEmployee($lockedUser, $employee))
                ->map(fn (mixed $employee): array => $this->workplaceAttributes($lockedUser, $employee))
                ->sortBy(fn (array $attributes): string => $this->workplaceSortKey($attributes))
                ->values();

            $primaryWorkplaceCount = $workplaces
                ->where('form_id', EmploymentForm::PRIMARY_WORKPLACE_ID)
                ->count();

            $lockedUser->workplaces()->delete();

            $workplaces->each(
                fn (array $attributes): Workplace => $lockedUser->workplaces()->create($attributes),
            );

            $degree = $this->resolveUserEvaluationCategory->handle($lockedUser);
            $degreeChanged = $lockedUser->degree !== $degree;

            if ($degreeChanged) {
                $lockedUser->update(['degree' => $degree]);
            }

            return [$degreeChanged, $primaryWorkplaceCount];
        }, attempts: 5);

        if ($primaryWorkplaceCount > 1) {
            Log::warning('HEMIS bir nechta asosiy ish joyini qaytardi.', [
                'user_id' => $user->getKey(),
                'hemis_id' => $user->hemis_id,
                'primary_workplace_count' => $primaryWorkplaceCount,
            ]);
        }

        $syncedUser = User::query()
            ->with(['ratingWorkplace.department', 'ratingWorkplace.position'])
            ->findOrFail($user->getKey());

        return new HemisWorkplaceSyncResult(
            user: $syncedUser,
            degreeChanged: $degreeChanged,
            primaryWorkplaceCount: $primaryWorkplaceCount,
        );
    }

    /** @param  array<string, int>  $attributes */
    private function workplaceSortKey(array $attributes): string
    {
        $priority = $attributes['form_id'] === EmploymentForm::PRIMARY_WORKPLACE_ID ? 0 : 1;

        return implode(':', array_map(
            static fn (int $value): string => str_pad((string) $value, 20, '0', STR_PAD_LEFT),
            [
                $priority,
                $attributes['form_id'],
                $attributes['department_id'],
                $attributes['staff_position_id'],
                $attributes['staff_id'],
                $attributes['academic_degree_id'],
                $attributes['academic_rank_id'],
                $attributes['status_id'],
                $attributes['type_id'],
            ],
        ));
    }

    /** @return array<string, int> */
    private function workplaceAttributes(User $user, mixed $employee): array
    {
        if (! is_array($employee)) {
            throw new UnexpectedValueException("HEMIS foydalanuvchi [{$user->getKey()}] ish joyini yaroqsiz formatda qaytardi.");
        }

        $employeeId = data_get($employee, 'id');

        if (is_numeric($employeeId) && (int) $employeeId !== (int) $user->getKey()) {
            throw new UnexpectedValueException('HEMIS boshqa foydalanuvchining ish joyini qaytardi.');
        }

        $departmentId = data_get($employee, 'department.id');

        if (! is_numeric($departmentId) || ! Department::query()->whereKey($departmentId)->exists()) {
            throw new UnexpectedValueException("HEMIS bo‘limi [{$departmentId}] lokal ma’lumotnomada topilmadi.");
        }

        return [
            'department_id' => (int) $departmentId,
            'academic_degree_id' => $this->syncReference(AcademicDegree::class, data_get($employee, 'academicDegree')),
            'academic_rank_id' => $this->syncReference(AcademicRank::class, data_get($employee, 'academicRank')),
            'form_id' => $this->syncReference(EmploymentForm::class, data_get($employee, 'employmentForm')),
            'staff_id' => $this->syncReference(EmploymentStaff::class, data_get($employee, 'employmentStaff')),
            'staff_position_id' => $this->syncReference(StaffPosition::class, data_get($employee, 'staffPosition')),
            'status_id' => $this->syncReference(EmployeeStatus::class, data_get($employee, 'employeeStatus')),
            'type_id' => $this->syncReference(EmployeeType::class, data_get($employee, 'employeeType')),
        ];
    }

    private function isWorkingEmployee(User $user, mixed $employee): bool
    {
        if (! is_array($employee)) {
            throw new UnexpectedValueException("HEMIS foydalanuvchi [{$user->getKey()}] ish joyini yaroqsiz formatda qaytardi.");
        }

        $statusId = data_get($employee, 'employeeStatus.code');

        if (! is_numeric($statusId)) {
            throw new UnexpectedValueException('HEMIS ish joyining xodim holatini yaroqli formatda qaytarmadi.');
        }

        return (int) $statusId === EmployeeStatus::WORKING_ID;
    }

    /** @param  class-string<Model>  $model */
    private function syncReference(string $model, mixed $reference): int
    {
        $id = data_get($reference, 'code');
        $name = data_get($reference, 'name');

        if (! is_numeric($id) || ! is_string($name) || $name === '') {
            throw new UnexpectedValueException("Invalid HEMIS reference for {$model}.");
        }

        return (int) $model::query()->updateOrCreate(
            ['id' => (int) $id],
            ['name' => $name],
        )->getKey();
    }
}
