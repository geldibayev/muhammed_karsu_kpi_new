<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\DatumResourceIdentifier;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalPartTimeUserDeletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 120_000;

    public function test_super_admin_can_deactivate_any_active_user_from_the_user_list(): void
    {
        Storage::fake('local');

        $this->assertFalse(Route::has('users.roles.update'));

        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create([
            'name' => $this->userName('Faolsizlantiriladigan Xodim'),
        ]);
        $userWithoutWorkplace = User::factory()->create([
            'name' => $this->userName('Ish Joyisiz Xodim'),
        ]);
        $faculty = $this->createDepartment('Umumiy fakultet');
        $department = $this->createDepartment('Umumiy kafedra', $faculty);
        $this->createWorkplace($target, $department, EmploymentForm::PRIMARY_WORKPLACE_ID);
        [$report, $criterion] = $this->createScoredCriterion();
        $path = 'uploads/deactivated-user.pdf';
        Storage::disk('local')->put($path, 'evidence');
        $datum = $this->createDatum($target, $criterion, 'accepted', 7, [
            'type' => 'file',
            'disk' => 'local',
            'path' => $path,
        ]);
        app(RecalculateReportPoints::class)->handle($report);

        $this->actingAs($superAdmin)
            ->get(route('users.roles.index'))
            ->assertOk()
            ->assertSee('Faolsizlantiriladigan Xodim')
            ->assertSee('Ish Joyisiz Xodim')
            ->assertSee(route('users.deactivation.update', $target))
            ->assertSee(route('users.deactivation.update', $userWithoutWorkplace))
            ->assertSee('Faolsizlantirish')
            ->assertSee('Foydalanuvchini izlash')
            ->assertDontSee('name="roles[]"', false)
            ->assertDontSee('Saqlash');

        $this->actingAs($superAdmin)
            ->get(route('users.roles.index', ['search' => 'Ish Joyisiz']))
            ->assertOk()
            ->assertSee('Ish Joyisiz Xodim')
            ->assertDontSee('Faolsizlantiriladigan Xodim')
            ->assertSee('value="Ish Joyisiz"', false);

        $this->actingAs($superAdmin)
            ->get(route('users.roles.index', ['search' => (string) $target->hemis_id]))
            ->assertOk()
            ->assertSee('Faolsizlantiriladigan Xodim')
            ->assertDontSee('Ish Joyisiz Xodim');

        $this->actingAs($superAdmin)
            ->patch(route('users.deactivation.update', $target))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('0', $target->fresh()->status);
        $this->assertSame('deleted', $datum->fresh()->status);
        $this->assertSame(0.0, $datum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'user_deactivated',
            'message' => 'Foydalanuvchi kafedra yoki fakultetda faol ishlamagani sabab faolsizlantirildi; resurs va uning balli barcha reytinglardan chiqarildi.',
        ]);
        $this->assertDatabaseMissing('criterion_points', ['user_id' => $target->getKey()]);
        $this->assertDatabaseMissing('points', ['user_id' => $target->getKey()]);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($superAdmin)
            ->get(route('ratings.index', ['mode' => 'without_degree']))
            ->assertOk()
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users
                ->getCollection()
                ->doesntContain(fn (User $user): bool => $user->is($target)));
        $this->actingAs($superAdmin)
            ->get(route('users.roles.index'))
            ->assertOk()
            ->assertSee('Faol emas')
            ->assertDontSee(route('users.deactivation.update', $target));
    }

    public function test_only_super_admin_can_deactivate_an_active_non_admin_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $otherSuperAdmin = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        $target = User::factory()->create();

        $this->patch(route('users.deactivation.update', $target))
            ->assertRedirect(route('login'));
        $this->actingAs($teacher)
            ->patch(route('users.deactivation.update', $target))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('users.deactivation.update', $superAdmin))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('users.deactivation.update', $otherSuperAdmin))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('users.deactivation.update', $target))
            ->assertRedirect();
        $this->actingAs($superAdmin)
            ->patch(route('users.deactivation.update', $target))
            ->assertForbidden();

        $this->assertSame('0', $target->fresh()->status);
        $this->assertSame('1', $superAdmin->fresh()->status);
        $this->assertSame('1', $otherSuperAdmin->fresh()->status);
    }

    public function test_super_admin_can_deactivate_external_part_timer_and_remove_all_rating_effects(): void
    {
        Storage::fake('local');

        $superAdmin = User::factory()->superAdmin()->create();
        $externalPartTimer = User::factory()->create([
            'name' => $this->userName('O‘chiriladigan Tashqi Xodim'),
        ]);
        $remainingTeacher = User::factory()->create([
            'name' => $this->userName('Reytingda Qoladigan Xodim'),
        ]);
        $faculty = $this->createDepartment('Sinov fakulteti');
        $department = $this->createDepartment('Sinov kafedrasi', $faculty);
        $this->createWorkplace($externalPartTimer, $department, EmploymentForm::EXTERNAL_PART_TIME_ID);
        $this->createWorkplace($remainingTeacher, $department, EmploymentForm::PRIMARY_WORKPLACE_ID);

        [$report, $criterion] = $this->createScoredCriterion();
        $path = 'uploads/external-part-timer.pdf';
        Storage::disk('local')->put($path, 'evidence');
        $acceptedDatum = $this->createDatum($externalPartTimer, $criterion, 'accepted', 8, [
            'type' => 'file',
            'disk' => 'local',
            'path' => $path,
        ]);
        $checkingDatum = $this->createDatum($externalPartTimer, $criterion, 'checking', 2);
        $remainingDatum = $this->createDatum($remainingTeacher, $criterion, 'accepted', 4);
        DatumResourceIdentifier::query()->create([
            'datum_id' => $acceptedDatum->getKey(),
            'report_id' => $report->getKey(),
            'user_id' => $externalPartTimer->getKey(),
            'type' => 'file_sha256',
            'value_hash' => str_repeat('a', 64),
            'active_value_hash' => str_repeat('a', 64),
        ]);
        app(RecalculateReportPoints::class)->handle($report);
        DB::table('sessions')->insert([
            'id' => 'external-part-timer-session',
            'user_id' => $externalPartTimer->getKey(),
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('users.external-part-timers.index'))
            ->assertOk()
            ->assertSee('O‘chiriladigan Tashqi Xodim')
            ->assertSee(route('users.external-part-timers.destroy', $externalPartTimer))
            ->assertSee('O‘chirish');

        $this->actingAs($superAdmin)
            ->delete(route('users.external-part-timers.destroy', $externalPartTimer))
            ->assertRedirect(route('users.external-part-timers.index'))
            ->assertSessionHas('success');

        $this->assertModelExists($externalPartTimer);
        $this->assertSame('0', $externalPartTimer->fresh()->status);
        $this->assertNull($externalPartTimer->fresh()->remember_token);
        $this->assertSame('deleted', $acceptedDatum->fresh()->status);
        $this->assertSame(0.0, $acceptedDatum->fresh()->point);
        $this->assertSame('deleted', $checkingDatum->fresh()->status);
        $this->assertSame(0.0, $checkingDatum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $acceptedDatum->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'user_deactivated',
        ]);
        $this->assertNull(DatumResourceIdentifier::query()
            ->whereBelongsTo($acceptedDatum)
            ->value('active_value_hash'));
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('sessions', ['user_id' => $externalPartTimer->getKey()]);
        $this->assertDatabaseMissing('criterion_points', ['user_id' => $externalPartTimer->getKey()]);
        $this->assertDatabaseMissing('points', ['user_id' => $externalPartTimer->getKey()]);
        $this->assertDatabaseHas('points', [
            'user_id' => $remainingTeacher->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 4,
        ]);
        $this->assertSame('accepted', $remainingDatum->fresh()->status);

        $this->actingAs($superAdmin)
            ->get(route('users.external-part-timers.index'))
            ->assertOk()
            ->assertDontSee('O‘chiriladigan Tashqi Xodim');
        $this->actingAs($superAdmin)
            ->get(route('ratings.index', ['mode' => 'without_degree']))
            ->assertOk()
            ->assertDontSee('O‘chiriladigan Tashqi Xodim')
            ->assertSee('Reytingda Qoladigan Xodim');
        $this->actingAs($superAdmin)
            ->get(route('ratings.index', ['mode' => 'departments']))
            ->assertOk()
            ->assertViewHas('unitRankings', function (LengthAwarePaginator $rankings) use ($department): bool {
                $row = $rankings->items()[0] ?? null;

                return is_array($row)
                    && $row['id'] === $department->getKey()
                    && $row['users_count'] === 1
                    && $row['total_points'] === 4.0
                    && $row['average_points'] === 4.0;
            });

        $this->actingAs($externalPartTimer->fresh())
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_only_super_admin_can_delete_external_only_user(): void
    {
        $teacher = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $externalPartTimer = User::factory()->create();
        $mixedEmployee = User::factory()->create([
            'name' => $this->userName('Asosiy Ish Joyli Xodim'),
        ]);
        $ordinaryUser = User::factory()->create();
        $department = $this->createDepartment('Chegara kafedrasi');
        $this->createWorkplace($externalPartTimer, $department, EmploymentForm::EXTERNAL_PART_TIME_ID);
        $this->createWorkplace($mixedEmployee, $department, EmploymentForm::EXTERNAL_PART_TIME_ID);
        $this->createWorkplace($mixedEmployee, $department, EmploymentForm::PRIMARY_WORKPLACE_ID);

        $this->delete(route('users.external-part-timers.destroy', $externalPartTimer))
            ->assertRedirect(route('login'));
        $this->actingAs($teacher)
            ->delete(route('users.external-part-timers.destroy', $externalPartTimer))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->delete(route('users.external-part-timers.destroy', $ordinaryUser))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->delete(route('users.external-part-timers.destroy', $mixedEmployee))
            ->assertForbidden();

        $this->assertSame('1', $externalPartTimer->fresh()->status);
        $this->assertSame('1', $mixedEmployee->fresh()->status);
        $this->actingAs($superAdmin)
            ->get(route('users.external-part-timers.index'))
            ->assertOk()
            ->assertSee('Asosiy Ish Joyli Xodim')
            ->assertSee('O‘chirib bo‘lmaydi');
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

    private function createWorkplace(User $user, Department $department, int $formId): Workplace
    {
        $degree = AcademicDegree::query()->create(['id' => $this->referenceId++, 'name' => 'PhD']);
        $rank = AcademicRank::query()->create(['id' => $this->referenceId++, 'name' => 'Dotsent']);
        $form = EmploymentForm::query()->firstOrCreate(['id' => $formId], [
            'name' => $formId === EmploymentForm::PRIMARY_WORKPLACE_ID
                ? 'Asosiy ish joyi'
                : 'O‘rindoshlik (tashqi)',
        ]);
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => 'Professor']);
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

    /** @return array{Report, Criterion} */
    private function createScoredCriterion(): array
    {
        Evaluation::query()->firstOrCreate([
            'code' => 'no_degrees',
        ], [
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Tashqi o‘rindoshlar hisoboti'],
            'status' => '1',
        ]);
        $rootCriterion = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Mezon'],
            'parent_id' => $rootCriterion->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 10,
        ]);

        return [$report, $criterion];
    }

    /** @param array<string, mixed> $material */
    private function createDatum(
        User $user,
        Criterion $criterion,
        string $status,
        float $point,
        array $material = ['type' => 'url', 'link' => 'https://example.com'],
    ): Datum {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => $material,
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
