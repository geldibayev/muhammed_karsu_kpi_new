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
use UnexpectedValueException;

class SyncHemisWorkplaces
{
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
            throw new UnexpectedValueException('HEMIS foydalanuvchi uchun ish joyi ma’lumotini qaytarmadi.');
        }

        [$degreeChanged, $primaryWorkplaceCount] = DB::transaction(function () use ($user, $employees): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $workplaces = collect($employees)
                ->map(fn (mixed $employee): array => $this->workplaceAttributes($lockedUser, $employee));

            $primaryWorkplaceCount = $workplaces
                ->where('form_id', EmploymentForm::PRIMARY_WORKPLACE_ID)
                ->count();

            if ($primaryWorkplaceCount > 1) {
                throw new UnexpectedValueException('HEMIS bir nechta asosiy ish joyini qaytardi.');
            }

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

        $syncedUser = User::query()
            ->with(['ratingWorkplace.department', 'ratingWorkplace.position'])
            ->findOrFail($user->getKey());

        return new HemisWorkplaceSyncResult(
            user: $syncedUser,
            degreeChanged: $degreeChanged,
            primaryWorkplaceCount: $primaryWorkplaceCount,
        );
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
