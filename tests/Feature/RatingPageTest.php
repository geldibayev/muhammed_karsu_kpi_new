<?php

namespace Tests\Feature;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\Point;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class RatingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 1000;

    public function test_guest_and_user_without_a_known_role_cannot_view_ratings(): void
    {
        $ratedUser = User::factory()->create();

        $this->get(route('ratings.index'))
            ->assertRedirect(route('login'));
        $this->get(route('ratings.show', $ratedUser))
            ->assertRedirect(route('login'));

        $user = User::factory()->withRole('unknown')->create();

        $this->actingAs($user)
            ->get(route('ratings.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('ratings.show', $ratedUser))
            ->assertForbidden();
    }

    public function test_users_are_ranked_by_active_report_and_show_hemis_workplace_data(): void
    {
        $viewer = User::factory()->create();
        $firstUser = User::factory()->create([
            'name' => $this->userName('Birinchi Ustoz'),
            'degree' => 'hold_degrees',
            'image' => json_encode(['min' => 'https://hemis.example/first.jpg'], JSON_THROW_ON_ERROR),
        ]);
        $secondUser = User::factory()->create([
            'name' => $this->userName('Ikkinchi Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $zeroPointUser = User::factory()->create([
            'name' => $this->userName('<script>alert(1)</script>'),
            'degree' => 'hold_degrees',
        ]);
        $withoutDegreeUser = User::factory()->create([
            'name' => $this->userName('Darajasiz Ustoz'),
            'degree' => 'no_degrees',
        ]);

        $mathematicsFaculty = $this->createDepartment('Matematika fakulteti');
        $algebraDepartment = $this->createDepartment('Algebra kafedrasi', $mathematicsFaculty);
        $this->createWorkplace($firstUser, $algebraDepartment, 'Dotsent');
        $this->createWorkplace($secondUser, $algebraDepartment, 'Assistent');

        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $firstCriterion = $this->createCriterion($activeReport, 'Birinchi mezon');
        $secondCriterion = $this->createCriterion($activeReport, 'Ikkinchi mezon');
        $oldCriterion = $this->createCriterion($oldReport, 'Eski mezon');

        $this->createPoint($firstUser, $firstCriterion, $activeReport, 7.5);
        $this->createPoint($firstUser, $secondCriterion, $activeReport, 4.5);
        $this->createPoint($secondUser, $firstCriterion, $activeReport, 5);
        $this->createPoint($secondUser, $oldCriterion, $oldReport, 100);
        $this->createPoint($withoutDegreeUser, $firstCriterion, $activeReport, 50);

        $response = $this->actingAs($viewer)->get(route('ratings.index'));

        $response
            ->assertOk()
            ->assertSee('Ilmiy darajaga ega')
            ->assertSee('Ilmiy darajaga ega emas')
            ->assertSee('Matematika fakulteti')
            ->assertSee('Algebra kafedrasi')
            ->assertSee('Dotsent')
            ->assertSee('https://hemis.example/first.jpg')
            ->assertSee('12.00')
            ->assertSee('5.00')
            ->assertSee(route('ratings.show', $firstUser))
            ->assertDontSee('Darajasiz Ustoz')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSeeInOrder(['Birinchi Ustoz', 'Ikkinchi Ustoz'])
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($firstUser, $secondUser, $zeroPointUser): bool {
                return $users->total() === 3
                    && $users->items()[0]->is($firstUser)
                    && (float) $users->items()[0]->total_points === 12.0
                    && $users->items()[1]->is($secondUser)
                    && (float) $users->items()[1]->total_points === 5.0
                    && $users->getCollection()->contains(fn (User $user): bool => $user->is($zeroPointUser));
            });

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['degree_group' => 'without_degree']))
            ->assertOk()
            ->assertSee('Darajasiz Ustoz')
            ->assertSee('50.00')
            ->assertDontSee('Birinchi Ustoz');
    }

    public function test_search_faculty_and_department_filters_can_be_combined(): void
    {
        $viewer = User::factory()->create();
        $firstFaculty = $this->createDepartment('Birinchi fakultet');
        $firstDepartment = $this->createDepartment('Birinchi kafedra', $firstFaculty);
        $secondFaculty = $this->createDepartment('Ikkinchi fakultet');
        $secondDepartment = $this->createDepartment('Ikkinchi kafedra', $secondFaculty);
        $matchingUser = User::factory()->create([
            'name' => $this->userName('Qidirilgan Olim'),
            'degree' => 'hold_degrees',
        ]);
        $otherUser = User::factory()->create([
            'name' => $this->userName('Boshqa Olim'),
            'degree' => 'hold_degrees',
        ]);
        $this->createWorkplace($matchingUser, $firstDepartment, 'Professor');
        $this->createWorkplace($otherUser, $secondDepartment, 'Assistent');

        $response = $this->actingAs($viewer)->get(route('ratings.index', [
            'search' => '  Qidirilgan   Olim ',
            'degree_group' => 'with_degree',
            'faculty' => $firstFaculty->getKey(),
            'department' => $firstDepartment->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertSee('Qidirilgan Olim')
            ->assertSee('Birinchi fakultet')
            ->assertSee('Birinchi kafedra')
            ->assertSee('Professor')
            ->assertDontSee('Boshqa Olim')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1);
    }

    public function test_invalid_rating_filters_are_rejected(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['degree_group' => 'invalid']))
            ->assertSessionHasErrors('degree_group');
    }

    public function test_rating_details_show_criterion_scores_and_attributed_evaluators(): void
    {
        $viewer = User::factory()->create();
        $ratedUser = User::factory()->create([
            'name' => $this->userName('Baholangan Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $reviewer = User::factory()->create([
            'name' => $this->userName('Mas’ul Baholovchi'),
        ]);
        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $firstSection = $this->createCriterion($activeReport, 'Birinchi bo‘lim');
        $secondSection = $this->createCriterion($activeReport, 'Ikkinchi bo‘lim');
        $oldSection = $this->createCriterion($oldReport, 'Eski bo‘lim');
        $manualCriterion = $this->createCriterion($activeReport, 'Qo‘lda baholangan kriteriya', [
            'checking' => 'manual',
            'parent_id' => $firstSection->getKey(),
        ]);
        $aiCriterion = $this->createCriterion($activeReport, 'AI baholagan kriteriya', [
            'checking' => 'ai',
            'ai_model' => 'gpt-test',
            'parent_id' => $firstSection->getKey(),
        ]);
        $systemCriterion = $this->createCriterion($activeReport, 'Auditsiz kriteriya', [
            'checking' => 'site:test',
            'parent_id' => $firstSection->getKey(),
        ]);
        $pendingCriterion = $this->createCriterion($activeReport, 'Baholanishi kutilayotgan kriteriya', [
            'checking' => 'manual',
            'parent_id' => $firstSection->getKey(),
        ]);
        $cancelledCriterion = $this->createCriterion($activeReport, 'Qaytarilgan kriteriya', [
            'parent_id' => $firstSection->getKey(),
        ]);
        $unuploadedCriterion = $this->createCriterion($activeReport, 'Yuklanmagan kriteriya', [
            'parent_id' => $secondSection->getKey(),
        ]);
        $oldCriterion = $this->createCriterion($oldReport, 'Eski kriteriya', [
            'parent_id' => $oldSection->getKey(),
        ]);
        $oldPendingCriterion = $this->createCriterion($oldReport, 'Eski baholanmagan kriteriya', [
            'parent_id' => $oldSection->getKey(),
        ]);

        $this->createPoint($ratedUser, $manualCriterion, $activeReport, 4.25);
        $this->createPoint($ratedUser, $aiCriterion, $activeReport, 3.5);
        $this->createPoint($ratedUser, $systemCriterion, $activeReport, 2);
        $this->createPoint($ratedUser, $oldCriterion, $oldReport, 99);

        $manualDatum = $this->createAcceptedDatum($ratedUser, $manualCriterion, 4.25);
        DatumHistory::query()->create([
            'datum_id' => $manualDatum->getKey(),
            'user_id' => $reviewer->getKey(),
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);
        $aiDatum = $this->createAcceptedDatum($ratedUser, $aiCriterion, 3.5);
        DatumHistory::query()->create([
            'datum_id' => $aiDatum->getKey(),
            'user_id' => $ratedUser->getKey(),
            'type' => 'success',
            'message' => 'AI tasdiqladi.',
            'message_type' => 'ai_evaluation',
        ]);
        $this->createPendingDatum($ratedUser, $pendingCriterion, 'received');
        $this->createPendingDatum($ratedUser, $pendingCriterion, 'checking');
        $this->createPendingDatum($ratedUser, $manualCriterion, 'checking');
        $this->createPendingDatum($ratedUser, $oldPendingCriterion, 'checking');
        $this->createPendingDatum($ratedUser, $cancelledCriterion, 'cancelled');

        $response = $this->actingAs($viewer)->get(route('ratings.show', [
            'user' => $ratedUser,
            'degree_group' => 'with_degree',
            'search' => 'Baholangan',
        ]));

        $response
            ->assertOk()
            ->assertSee('Baholangan Ustoz')
            ->assertSee('Birinchi bo‘lim')
            ->assertSee('#1')
            ->assertSee("1/{$manualCriterion->getKey()}")
            ->assertSee('Qo‘lda baholangan kriteriya')
            ->assertSee('4.25')
            ->assertSee('Mas’ul Baholovchi')
            ->assertSee('AI baholagan kriteriya')
            ->assertSee('3.50')
            ->assertSee('Sun’iy intellekt (gpt-test)')
            ->assertSee('Auditsiz kriteriya')
            ->assertSee('Auditda qayd etilmagan')
            ->assertSee('Baholanishi kutilayotgan kriteriya')
            ->assertSee('Baholanmagan')
            ->assertSee('Baholash kutilmoqda')
            ->assertSee('2 ta baholanmagan yuklama')
            ->assertSee('1 ta baholanmagan yuklama')
            ->assertSee('Qaytarilgan kriteriya')
            ->assertSee('Qaytarilgan')
            ->assertSee('Ikkinchi bo‘lim')
            ->assertSee('#2')
            ->assertSee("2/{$unuploadedCriterion->getKey()}")
            ->assertSee('Yuklanmagan kriteriya')
            ->assertSee('Yuklanmagan')
            ->assertSee('Ma’lumot yuklanmagan')
            ->assertSee('Jami: 9.75')
            ->assertDontSee('Eski kriteriya')
            ->assertDontSee('Eski baholanmagan kriteriya')
            ->assertDontSee('99.00')
            ->assertSee(route('ratings.index', [
                'search' => 'Baholangan',
                'degree_group' => 'with_degree',
            ]));

        $this->assertSame(1, substr_count($response->getContent(), 'Baholanishi kutilayotgan kriteriya'));

        $this->get(route('ratings.show', PHP_INT_MAX))->assertNotFound();
    }

    public function test_ratings_page_handles_the_absence_of_an_active_report(): void
    {
        $viewer = User::factory()->create(['degree' => 'hold_degrees']);

        $this->actingAs($viewer)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Faol hisobot topilmadi')
            ->assertViewHas('report', null);

        $this->actingAs($viewer)
            ->get(route('ratings.show', $viewer))
            ->assertOk()
            ->assertSee('Faol hisobot uchun kriteriyalar mavjud emas')
            ->assertSee('Jami: 0.00');
    }

    /** @return array<string, string> */
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

    private function createWorkplace(User $user, Department $department, string $positionName): Workplace
    {
        $academicDegree = AcademicDegree::query()->create(['id' => $this->referenceId++, 'name' => 'PhD']);
        $academicRank = AcademicRank::query()->create(['id' => $this->referenceId++, 'name' => 'Dotsent']);
        $form = EmploymentForm::query()->create(['id' => $this->referenceId++, 'name' => 'Asosiy ish joyi']);
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => $positionName]);
        $status = EmployeeStatus::query()->create(['id' => $this->referenceId++, 'name' => 'Ishlamoqda']);
        $type = EmployeeType::query()->create(['id' => $this->referenceId++, 'name' => 'Professor-o‘qituvchi']);

        return Workplace::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $department->getKey(),
            'academic_degree_id' => $academicDegree->getKey(),
            'academic_rank_id' => $academicRank->getKey(),
            'form_id' => $form->getKey(),
            'staff_id' => $staff->getKey(),
            'staff_position_id' => $position->getKey(),
            'status_id' => $status->getKey(),
            'type_id' => $type->getKey(),
        ]);
    }

    private function createReport(string $name, string $status): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(Report $report, string $name, array $attributes = []): Criterion
    {
        return Criterion::query()->create(array_merge([
            'name' => ['uz' => $name],
            'report_id' => $report->getKey(),
            'status' => '1',
        ], $attributes));
    }

    private function createAcceptedDatum(User $user, Criterion $criterion, float $point): Datum
    {
        return Datum::query()->create([
            'name' => 'Tasdiqlangan resurs',
            'material' => [],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }

    private function createPendingDatum(User $user, Criterion $criterion, string $status): Datum
    {
        return Datum::query()->create([
            'name' => 'Baholanmagan resurs',
            'material' => [],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => 0,
        ]);
    }

    private function createPoint(User $user, Criterion $criterion, Report $report, float $point): Point
    {
        return Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => $point,
        ]);
    }
}
