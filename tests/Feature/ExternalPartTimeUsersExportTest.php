<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExternalPartTimeUsersExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 90_000;

    public function test_only_super_admin_can_export_external_part_time_users(): void
    {
        $this->get(route('users.external-part-timers.export'))
            ->assertRedirect(route('login'));

        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get(route('users.external-part-timers.export'))
            ->assertForbidden();
    }

    public function test_super_admin_can_download_external_part_time_users_as_xlsx(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $faculty = $this->createDepartment('Tashqi o‘rindoshlar fakulteti');
        $department = $this->createDepartment('Tashqi o‘rindoshlar kafedrasi', $faculty);
        $secondDepartment = $this->createDepartment('Ikkinchi tashqi kafedra', $faculty);
        $externalPartTimer = User::factory()->create([
            'hemis_id' => 3_462_111_006,
            'name' => $this->userName('Tashqi O‘rindosh Xodim'),
        ]);
        $primaryEmployee = User::factory()->create([
            'name' => $this->userName('Asosiy Ish Joyidagi Xodim'),
        ]);

        $this->createWorkplace(
            $externalPartTimer,
            $department,
            EmploymentForm::EXTERNAL_PART_TIME_ID,
            'O‘rindoshlik (tashqi)',
            'Professor',
        );
        $this->createWorkplace(
            $primaryEmployee,
            $department,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            'Asosiy ish joy',
            'Dotsent',
        );
        $this->createWorkplace(
            $externalPartTimer,
            $secondDepartment,
            EmploymentForm::EXTERNAL_PART_TIME_ID,
            'O‘rindoshlik (tashqi)',
            'Katta o‘qituvchi',
        );

        $this->actingAs($superAdmin)
            ->get(route('users.roles.index'))
            ->assertOk()
            ->assertSee('Tashqi o‘rindoshlarni Excelga yuklash')
            ->assertSee(route('users.external-part-timers.export'));

        $response = $this->actingAs($superAdmin)
            ->get(route('users.external-part-timers.export'));

        $response
            ->assertOk()
            ->assertDownload()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet);
        $this->assertStringContainsString('HEMIS ID', $sheet);
        $this->assertStringContainsString('3462111006', $sheet);
        $this->assertStringContainsString('Tashqi O‘rindosh Xodim', $sheet);
        $this->assertStringContainsString('Tashqi o‘rindoshlar fakulteti', $sheet);
        $this->assertStringContainsString('Tashqi o‘rindoshlar kafedrasi', $sheet);
        $this->assertStringContainsString('Ikkinchi tashqi kafedra', $sheet);
        $this->assertStringContainsString('Professor', $sheet);
        $this->assertStringContainsString('Katta o‘qituvchi', $sheet);
        $this->assertStringContainsString('O‘rindoshlik (tashqi)', $sheet);
        $this->assertStringNotContainsString('Asosiy Ish Joyidagi Xodim', $sheet);
        $this->assertSame(1, substr_count($sheet, 'Tashqi O‘rindosh Xodim'));
    }

    /** @return array{full: string, first: string, last: string, third: string, short: string} */
    private function userName(string $fullName): array
    {
        return [
            'full' => $fullName,
            'first' => $fullName,
            'last' => '',
            'third' => '',
            'short' => $fullName,
        ];
    }

    private function createDepartment(string $name, ?Department $parent = null): Department
    {
        return Department::query()->create([
            'id' => $this->referenceId++,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
            'parent_id' => $parent?->getKey(),
        ]);
    }

    private function createWorkplace(
        User $user,
        Department $department,
        int $formId,
        string $formName,
        string $positionName,
    ): Workplace {
        $degree = AcademicDegree::query()->create(['id' => $this->referenceId++, 'name' => 'PhD']);
        $rank = AcademicRank::query()->create(['id' => $this->referenceId++, 'name' => 'Dotsent']);
        $form = EmploymentForm::query()->firstOrCreate(['id' => $formId], ['name' => $formName]);
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => $positionName]);
        $status = EmployeeStatus::query()->create(['id' => $this->referenceId++, 'name' => 'Ishlamoqda']);
        $type = EmployeeType::query()->create(['id' => $this->referenceId++, 'name' => 'Professor-o‘qituvchi']);

        return Workplace::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $department->getKey(),
            'academic_degree_id' => $degree->getKey(),
            'academic_rank_id' => $rank->getKey(),
            'form_id' => $form->getKey(),
            'staff_id' => $staff->getKey(),
            'staff_position_id' => $position->getKey(),
            'status_id' => $status->getKey(),
            'type_id' => $type->getKey(),
        ]);
    }
}
