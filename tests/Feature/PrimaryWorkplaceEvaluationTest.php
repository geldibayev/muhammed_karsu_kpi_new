<?php

namespace Tests\Feature;

use App\Actions\PaginateRatingUsers;
use App\Actions\RecalculateReportPoints;
use App\Actions\SyncHemisWorkplaces;
use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;
use UnexpectedValueException;

class PrimaryWorkplaceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.hemis.employee_api_url', 'https://hemis.test/employees');
        config()->set('services.hemis.api_key', 'test-token');
        Http::preventStrayRequests();

        $this->createDepartment(201, 'Rektorat');
        $this->createDepartment(202, 'Matematika kafedrasi');
    }

    public function test_hemis_sync_uses_only_primary_workplace_for_rating_category(): void
    {
        $user = User::factory()->create([
            'hemis_id' => 3172011004,
            'degree' => 'hold_degrees',
        ]);

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(202, 12, 11, 102, 'O‘qituvchi'),
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                    ],
                ],
            ]),
        ]);

        app(SyncHemisWorkplaces::class)->handle($user);

        $this->assertDatabaseCount('workplaces', 2);
        $this->assertSame('no_degrees', $user->fresh()->degree);
        $this->assertSame(101, $user->fresh()->primaryWorkplace?->staff_position_id);
        $this->assertSame('Dekan', $user->fresh()->primaryWorkplace?->position?->name);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://hemis.test/employees')
            && $request['search'] === 3172011004
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_primary_workplace_does_not_depend_on_hemis_result_order_or_department(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                        $this->employee(201, 12, 11, 102, 'O‘qituvchi'),
                    ],
                ],
            ]),
        ]);

        app(SyncHemisWorkplaces::class)->handle($user);

        $this->assertSame(2, $user->fresh()->workplaces()->count());
        $this->assertSame(EmploymentForm::PRIMARY_WORKPLACE_ID, $user->fresh()->primaryWorkplace?->form_id);
        $this->assertSame(101, $user->fresh()->primaryWorkplace?->staff_position_id);
        $this->assertSame('no_degrees', $user->fresh()->degree);
    }

    public function test_user_without_primary_workplace_is_not_included_in_rating(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(202, 12, 11, 102, 'O‘qituvchi'),
                    ],
                ],
            ]),
        ]);

        app(SyncHemisWorkplaces::class)->handle($user);

        $users = app(PaginateRatingUsers::class)->handle(null, [
            'degree_group' => 'without_degree',
        ]);

        $this->assertSame('no_degrees', $user->fresh()->degree);
        $this->assertNull($user->fresh()->primaryWorkplace);
        $this->assertSame(0, $users->total());
    }

    public function test_multiple_primary_workplaces_are_rejected_without_losing_existing_data(): void
    {
        $user = User::factory()->create();
        $this->createStoredWorkplace($user, 201, EmploymentForm::PRIMARY_WORKPLACE_ID, 10, 101);

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                        $this->employee(202, 11, 11, 102, 'O‘qituvchi'),
                    ],
                ],
            ]),
        ]);

        try {
            app(SyncHemisWorkplaces::class)->handle($user);
            $this->fail('Bir nechta asosiy ish joyi qabul qilindi.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('bir nechta asosiy ish joyi', $exception->getMessage());
        }

        $this->assertSame(1, $user->fresh()->workplaces()->count());
        $this->assertSame(101, $user->fresh()->primaryWorkplace?->staff_position_id);
    }

    public function test_backfill_command_supports_dry_run_and_updates_existing_users(): void
    {
        $user = User::factory()->create([
            'hemis_id' => 3172011004,
            'degree' => 'hold_degrees',
        ]);
        $this->createStoredWorkplace($user, 201, EmploymentForm::PRIMARY_WORKPLACE_ID, 10, 101);
        $this->createStoredWorkplace($user, 202, 12, 11, 102);
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol hisobot'],
            'status' => '1',
        ]);
        $this->mock(
            RecalculateReportPoints::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('handle')
                ->once()
                ->withArgs(fn (Report $activeReport): bool => $activeReport->is($report)),
        );

        $this->artisan('kpi:ratings:backfill-primary-workplaces', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run yakunlandi')
            ->assertSuccessful();

        $this->assertSame('hold_degrees', $user->fresh()->degree);

        $this->artisan('kpi:ratings:backfill-primary-workplaces')
            ->expectsOutputToContain('Reyting kategoriyalari yangilandi')
            ->assertSuccessful();

        $this->assertSame('no_degrees', $user->fresh()->degree);
    }

    /** @return array<string, mixed> */
    private function employee(
        int $departmentId,
        int $formId,
        int $degreeId,
        int $positionId,
        string $positionName,
    ): array {
        return [
            'department' => ['id' => $departmentId],
            'academicDegree' => ['code' => $degreeId, 'name' => $degreeId > 10 ? 'PhD' : 'Darajasiz'],
            'academicRank' => ['code' => 10, 'name' => 'Ilmiy unvonsiz'],
            'employmentForm' => [
                'code' => $formId,
                'name' => $formId === EmploymentForm::PRIMARY_WORKPLACE_ID
                    ? 'Asosiy ish joy'
                    : 'O‘rindoshlik (ichki)',
            ],
            'employmentStaff' => ['code' => $formId, 'name' => $formId === 11 ? '1 stavka' : '0.5 stavka'],
            'staffPosition' => ['code' => $positionId, 'name' => $positionName],
            'employeeStatus' => ['code' => 1, 'name' => 'Ishlamoqda'],
            'employeeType' => ['code' => 1, 'name' => 'Xodim'],
        ];
    }

    private function createDepartment(int $id, string $name): Department
    {
        return Department::query()->create([
            'id' => $id,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
        ]);
    }

    private function createStoredWorkplace(
        User $user,
        int $departmentId,
        int $formId,
        int $degreeId,
        int $positionId,
    ): Workplace {
        AcademicDegree::query()->firstOrCreate(['id' => $degreeId], ['name' => 'Daraja']);
        AcademicRank::query()->firstOrCreate(['id' => 10], ['name' => 'Unvonsiz']);
        EmploymentForm::query()->firstOrCreate(['id' => $formId], ['name' => 'Mehnat shakli']);
        EmploymentStaff::query()->firstOrCreate(['id' => $formId], ['name' => 'Stavka']);
        StaffPosition::query()->firstOrCreate(['id' => $positionId], ['name' => 'Lavozim']);
        EmployeeStatus::query()->firstOrCreate(['id' => 1], ['name' => 'Ishlamoqda']);
        EmployeeType::query()->firstOrCreate(['id' => 1], ['name' => 'Xodim']);

        return Workplace::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $departmentId,
            'academic_degree_id' => $degreeId,
            'academic_rank_id' => 10,
            'form_id' => $formId,
            'staff_id' => $formId,
            'staff_position_id' => $positionId,
            'status_id' => 1,
            'type_id' => 1,
        ]);
    }
}
