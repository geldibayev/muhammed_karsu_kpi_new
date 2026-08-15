<?php

namespace Tests\Feature;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
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

class CriterionRatingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 20_000;

    public function test_guest_and_unknown_role_cannot_view_criterion_rating(): void
    {
        $criterion = $this->createCriterion();

        $this->get(route('criteria.ratings.show', $criterion))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->withRole('unknown')->create())
            ->get(route('criteria.ratings.show', $criterion))
            ->assertForbidden();
    }

    public function test_users_are_ranked_together_by_criterion_point_without_degree_groups(): void
    {
        $viewer = User::factory()->create();
        $withDegree = User::factory()->create([
            'name' => $this->userName('Darajali Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $withoutDegree = User::factory()->create([
            'name' => $this->userName('Darajasiz Ustoz'),
            'degree' => 'no_degrees',
        ]);
        $administrativeEmployee = User::factory()->create([
            'name' => $this->userName('Ma’muriy Xodim'),
            'degree' => 'hold_degrees',
        ]);
        $faculty = $this->createDepartment('Test fakulteti');
        $department = $this->createDepartment('Test kafedrasi', $faculty);
        $administrativeDepartment = $this->createDepartment('Test ma’muriy bo‘limi');
        $this->createWorkplace($withDegree, $department, 'Professor');
        $this->createWorkplace($withoutDegree, $department, 'O‘qituvchi');
        $this->createWorkplace($administrativeEmployee, $administrativeDepartment, 'Bo‘lim boshlig‘i');
        $criterion = $this->createCriterion('Kriteriya reytingi');

        $this->createPoint($withDegree, $criterion, 5.5);
        $this->createPoint($withoutDegree, $criterion, 9);
        $this->createPoint($administrativeEmployee, $criterion, 100);
        $withDegreeAccepted = $this->createDatum($withDegree, $criterion, 'accepted');
        $withoutDegreeAccepted = $this->createDatum($withoutDegree, $criterion, 'accepted');
        $withoutDegreeSecondAccepted = $this->createDatum($withoutDegree, $criterion, 'accepted');
        $cancelled = $this->createDatum($withoutDegree, $criterion, 'cancelled');
        $otherCriterionAccepted = $this->createDatum(
            $withoutDegree,
            $this->createSiblingCriterion($criterion, 'Boshqa kriteriya', 'manual'),
            'accepted',
        );

        $response = $this->actingAs($viewer)
            ->get(route('criteria.ratings.show', $criterion));

        $response
            ->assertOk()
            ->assertSee('Kriteriya reytingi')
            ->assertSee('Darajali Ustoz')
            ->assertSee('Darajasiz Ustoz')
            ->assertDontSee('Ma’muriy Xodim')
            ->assertSee(route('ratings.show', $withDegree))
            ->assertSee(route('ratings.show', $withoutDegree))
            ->assertSee('Tasdiqlangan resurslar')
            ->assertSee('data-testid="criterion-rating-resources-toggle"', false)
            ->assertSee(route('upload.details', $withDegreeAccepted))
            ->assertSee(route('upload.details', $withoutDegreeAccepted))
            ->assertSee(route('upload.details', $withoutDegreeSecondAccepted))
            ->assertDontSee(route('upload.details', $cancelled))
            ->assertDontSee(route('upload.details', $otherCriterionAccepted))
            ->assertSeeInOrder(['2 ta', '9.00'])
            ->assertSeeInOrder(['1 ta', '5.50'])
            ->assertSeeInOrder(['Darajasiz Ustoz', 'Darajali Ustoz'])
            ->assertViewHas('rankedPoints', function (LengthAwarePaginator $points) use ($withoutDegree, $withDegree): bool {
                return $points->total() === 2
                    && $points->items()[0]->user->is($withoutDegree)
                    && $points->items()[0]->user->submissions->count() === 2
                    && (float) $points->items()[0]->point === 9.0
                    && $points->items()[1]->user->is($withDegree)
                    && $points->items()[1]->user->submissions->count() === 1
                    && (float) $points->items()[1]->point === 5.5;
            });
    }

    public function test_home_shows_rating_action_ai_and_assigned_reviewer_name(): void
    {
        $viewer = User::factory()->create();
        $reviewer = User::factory()->create([
            'name' => $this->userName('Masul Tekshiruvchi'),
        ]);
        $manualCriterion = $this->createCriterion('Manual mezon');
        $aiCriterion = $this->createSiblingCriterion($manualCriterion, 'AI mezon', 'ai');

        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $manualCriterion->id,
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1/'.$manualCriterion->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Masul Tekshiruvchi')
            ->assertSee('Sunʼiy intellekt')
            ->assertSee(route('criteria.ratings.show', $manualCriterion))
            ->assertSee(route('criteria.ratings.show', $aiCriterion));
    }

    private function createCriterion(string $name = 'Test kriteriyasi'): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol hisobot'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Asosiy bo‘lim'],
            'report_id' => $report->id,
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => $name],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createSiblingCriterion(
        Criterion $criterion,
        string $name,
        string $checking,
    ): Criterion {
        return Criterion::query()->create([
            'name' => ['uz' => $name],
            'parent_id' => $criterion->parent_id,
            'report_id' => $criterion->report_id,
            'checking' => $checking,
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createPoint(User $user, Criterion $criterion, float $point): Point
    {
        return Point::query()->create([
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'report_id' => $criterion->report_id,
            'point' => $point,
        ]);
    }

    private function createDatum(User $user, Criterion $criterion, string $status): Datum
    {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $status === 'accepted' ? 1 : 0,
        ]);
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
        $form = EmploymentForm::query()->firstOrCreate([
            'id' => EmploymentForm::PRIMARY_WORKPLACE_ID,
        ], [
            'name' => 'Asosiy ish joyi',
        ]);
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => $positionName]);
        $status = EmployeeStatus::query()->create(['id' => $this->referenceId++, 'name' => 'Ishlamoqda']);
        $type = EmployeeType::query()->create(['id' => $this->referenceId++, 'name' => 'Xodim']);

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

    /** @return array<string, string> */
    private function userName(string $full): array
    {
        return [
            'full' => $full,
            'first' => $full,
            'last' => '',
            'third' => '',
            'short' => $full,
        ];
    }
}
