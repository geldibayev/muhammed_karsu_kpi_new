<?php

namespace Tests\Feature;

use App\Actions\PaginateRatingUsers;
use App\Actions\RecalculateReportPoints;
use App\Actions\SyncHemisWorkplaces;
use App\Actions\SyncHemisWorkplacesForLogin;
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
        $faculty = $this->createDepartment(203, 'Matematika fakulteti');
        $this->createDepartment(202, 'Matematika kafedrasi', $faculty);
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

        $result = app(SyncHemisWorkplaces::class)->handle($user);

        $this->assertDatabaseCount('workplaces', 2);
        $this->assertTrue($result->degreeChanged);
        $this->assertSame(1, $result->primaryWorkplaceCount);
        $this->assertSame('no_degrees', $user->fresh()->degree);
        $this->assertSame(101, $user->fresh()->primaryWorkplace?->staff_position_id);
        $this->assertSame(101, $user->fresh()->ratingWorkplace?->staff_position_id);
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

    public function test_login_sync_recalculates_active_reports_when_category_changes(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
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
        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                    ],
                ],
            ]),
        ]);

        $syncedUser = app(SyncHemisWorkplacesForLogin::class)->handle($user);

        $this->assertSame('no_degrees', $syncedUser->degree);
    }

    public function test_user_without_primary_workplace_uses_additional_workplace_in_rating(): void
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
            'degree_group' => 'with_degree',
        ]);

        $this->assertSame('hold_degrees', $user->fresh()->degree);
        $this->assertNull($user->fresh()->primaryWorkplace);
        $this->assertSame(12, $user->fresh()->ratingWorkplace?->form_id);
        $this->assertSame(1, $users->total());
    }

    public function test_lowest_additional_employment_form_is_used_when_primary_is_missing(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $this->createStoredWorkplace($user, 201, 13, 11, 101);
        $this->createStoredWorkplace($user, 202, 12, 11, 102);

        $this->assertNull($user->fresh()->primaryWorkplace);
        $this->assertSame(12, $user->fresh()->ratingWorkplace?->form_id);
        $this->assertSame(202, $user->fresh()->ratingWorkplace?->department_id);
    }

    public function test_multiple_primary_workplaces_are_preserved_with_a_deterministic_rating_workplace(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
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

        $result = app(SyncHemisWorkplaces::class)->handle($user);

        $this->assertSame(2, $result->primaryWorkplaceCount);
        $this->assertSame(2, $user->fresh()->workplaces()->count());
        $this->assertSame(101, $user->fresh()->primaryWorkplace?->staff_position_id);
        $this->assertSame(101, $user->fresh()->ratingWorkplace?->staff_position_id);
        $this->assertSame('no_degrees', $user->fresh()->degree);

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(202, 11, 11, 102, 'O‘qituvchi'),
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                    ],
                ],
            ]),
        ]);

        app(SyncHemisWorkplaces::class)->handle($user);

        $this->assertSame(101, $user->fresh()->ratingWorkplace?->staff_position_id);
        $this->assertSame('no_degrees', $user->fresh()->degree);
    }

    public function test_user_with_multiple_primary_workplaces_remains_visible_in_rating(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $this->createStoredWorkplace($user, 202, EmploymentForm::PRIMARY_WORKPLACE_ID, 10, 101);
        $this->createStoredWorkplace($user, 202, EmploymentForm::PRIMARY_WORKPLACE_ID, 11, 102);

        $users = app(PaginateRatingUsers::class)->handle(null, [
            'degree_group' => 'with_degree',
        ]);

        $this->assertSame(1, $users->total());
    }

    public function test_empty_hemis_result_does_not_delete_existing_workplaces(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $workplace = $this->createStoredWorkplace(
            $user,
            201,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            11,
            101,
        );

        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => ['items' => []],
            ]),
        ]);

        try {
            app(SyncHemisWorkplaces::class)->handle($user);
            $this->fail('Bo‘sh HEMIS javobi qabul qilindi.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('ish joyi ma’lumotini qaytarmadi', $exception->getMessage());
        }

        $this->assertModelExists($workplace);
        $this->assertSame('hold_degrees', $user->fresh()->degree);
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

    public function test_backfill_uses_additional_workplace_when_primary_is_missing(): void
    {
        $user = User::factory()->create([
            'hemis_id' => 3172011004,
            'degree' => 'no_degrees',
        ]);
        $this->createStoredWorkplace($user, 202, 12, 11, 102);

        $this->artisan('kpi:ratings:backfill-primary-workplaces')
            ->expectsOutputToContain('Qo‘shimcha ish joyidan baholanadi')
            ->assertSuccessful();

        $this->assertSame('hold_degrees', $user->fresh()->degree);
        $this->assertSame(12, $user->fresh()->ratingWorkplace?->form_id);
    }

    public function test_sync_hemis_option_repairs_only_problematic_users_and_recalculates_once(): void
    {
        $problematicUser = User::factory()->create([
            'hemis_id' => 3172011004,
            'degree' => 'hold_degrees',
        ]);
        $correctUser = User::factory()->create([
            'hemis_id' => 3172011005,
            'degree' => 'no_degrees',
        ]);
        $this->createStoredWorkplace(
            $correctUser,
            201,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            10,
            101,
        );
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

        $this->artisan('kpi:ratings:backfill-primary-workplaces', [
            '--sync-hemis' => true,
            '--delay' => 0,
        ])
            ->expectsOutputToContain('HEMISdan qayta sinxronlanadigan foydalanuvchilar: 1')
            ->expectsOutputToContain('faol hisobot ballari qayta hisoblandi')
            ->assertSuccessful();

        $this->assertSame(2, $problematicUser->fresh()->workplaces()->count());
        $this->assertSame(1, $problematicUser->fresh()->primaryWorkplaces()->count());
        $this->assertSame('no_degrees', $problematicUser->fresh()->degree);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['search'] === 3172011004);
    }

    public function test_sync_hemis_dry_run_makes_no_http_request_or_database_change(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $firstWorkplace = $this->createStoredWorkplace(
            $user,
            201,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            10,
            101,
        );
        $secondWorkplace = $this->createStoredWorkplace(
            $user,
            202,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            11,
            102,
        );
        Http::fake();

        $this->artisan('kpi:ratings:backfill-primary-workplaces', [
            '--dry-run' => true,
            '--sync-hemis' => true,
        ])
            ->expectsOutputToContain('HEMISdan qayta sinxronlanadigan foydalanuvchilar: 0')
            ->expectsOutputToContain('HEMISga so‘rov yuborilmadi')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertModelExists($firstWorkplace);
        $this->assertModelExists($secondWorkplace);
        $this->assertSame('hold_degrees', $user->fresh()->degree);
    }

    public function test_default_sync_does_not_refresh_valid_additional_workplace_fallback(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $this->createStoredWorkplace($user, 202, 12, 11, 102);
        Http::fake();

        $this->artisan('kpi:ratings:backfill-primary-workplaces', [
            '--sync-hemis' => true,
            '--delay' => 0,
        ])
            ->expectsOutputToContain('HEMISdan qayta sinxronlanadigan foydalanuvchilar: 0')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(12, $user->fresh()->ratingWorkplace?->form_id);
    }

    public function test_all_users_option_refreshes_users_that_already_have_a_primary_workplace(): void
    {
        $firstUser = User::factory()->create([
            'hemis_id' => 3172011004,
            'degree' => 'no_degrees',
        ]);
        $secondUser = User::factory()->create([
            'hemis_id' => 3172011005,
            'degree' => 'no_degrees',
        ]);
        $this->createStoredWorkplace(
            $firstUser,
            201,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            10,
            101,
        );
        $this->createStoredWorkplace(
            $secondUser,
            202,
            EmploymentForm::PRIMARY_WORKPLACE_ID,
            10,
            102,
        );
        Http::fake([
            'https://hemis.test/employees*' => Http::response([
                'data' => [
                    'items' => [
                        $this->employee(201, 11, 10, 101, 'Dekan'),
                        $this->employee(202, 12, 11, 102, 'O‘qituvchi'),
                    ],
                ],
            ]),
        ]);

        $this->artisan('kpi:ratings:backfill-primary-workplaces', [
            '--sync-hemis' => true,
            '--all-users' => true,
            '--delay' => 0,
        ])
            ->expectsOutputToContain('HEMISdan qayta sinxronlanadigan foydalanuvchilar: 2')
            ->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(1, $firstUser->fresh()->primaryWorkplaces()->count());
        $this->assertSame(1, $secondUser->fresh()->primaryWorkplaces()->count());
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

    private function createDepartment(int $id, string $name, ?Department $parent = null): Department
    {
        return Department::query()->create([
            'id' => $id,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
            'parent_id' => $parent?->getKey(),
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
