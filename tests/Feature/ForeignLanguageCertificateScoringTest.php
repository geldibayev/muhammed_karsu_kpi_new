<?php

namespace Tests\Feature;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
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
use App\Support\ForeignLanguageCertificateCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForeignLanguageCertificateScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 5000;

    #[DataProvider('scoreMatrix')]
    public function test_server_score_matrix(
        string $level,
        string $degree,
        bool $specialDepartment,
        float $expectedPoint,
    ): void {
        config()->set('kpi.foreign_language_faculty_department_id', 1);
        config()->set('kpi.russian_language_department_id', 23);

        $departmentId = $specialDepartment ? 21 : 23;
        $facultyId = 1;

        $this->assertSame(
            $expectedPoint,
            ForeignLanguageCertificateCriterionRule::pointFor(
                $level,
                $degree,
                $departmentId,
                $facultyId,
            ),
        );
    }

    public function test_reviewer_only_selects_level_and_server_persists_special_department_score(): void
    {
        $fixture = $this->context();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $this->workplace($owner, $fixture['specialDepartment']);
        $datum = $this->datum($owner, $fixture['criterion'], 'checking');

        $this->actingAs($fixture['reviewer'])
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('faqat sertifikat darajasini tasdiqlaydi')
            ->assertSee('C1 sertifikat')
            ->assertSee('3.00 ball');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $fixture['options']['c1']->getKey(),
                'point' => 99,
            ])
            ->assertSessionHasErrors('point');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $fixture['options']['c1']->getKey(),
            ])
            ->assertRedirect(route('reviews.index'));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(3.0, $datum->point);
        $this->assertSame($fixture['options']['c1']->getKey(), $datum->manual_score_option_id);
    }

    public function test_russian_language_department_uses_general_score_rule(): void
    {
        $fixture = $this->context();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $this->workplace($owner, $fixture['russianDepartment']);
        $datum = $this->datum($owner, $fixture['criterion'], 'received');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $fixture['options']['c1']->getKey(),
            ])
            ->assertRedirect(route('reviews.index'));

        $this->assertSame(8.0, $datum->fresh()->point);
    }

    public function test_workplace_attached_directly_to_foreign_language_faculty_uses_special_score_rule(): void
    {
        $this->context();

        $this->assertSame(
            3.0,
            ForeignLanguageCertificateCriterionRule::pointFor(
                'c1',
                'no_degrees',
                (int) config('kpi.foreign_language_faculty_department_id'),
                null,
            ),
        );
    }

    public function test_cancelled_override_also_accepts_only_level_and_uses_server_score(): void
    {
        $fixture = $this->context();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $this->workplace($owner, $fixture['specialDepartment']);
        $datum = $this->datum($owner, $fixture['criterion'], 'cancelled');

        $this->actingAs($fixture['reviewer'])
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Sertifikat darajasi')
            ->assertSee('C2 sertifikat');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                'score_option_id' => $fixture['options']['c2']->getKey(),
                'point' => 1,
            ])
            ->assertSessionHasErrors('point');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                'score_option_id' => $fixture['options']['c2']->getKey(),
            ])
            ->assertRedirect(route('upload.details', $datum));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(6.0, $datum->point);
        $this->assertSame($fixture['options']['c2']->getKey(), $datum->manual_score_option_id);
    }

    public function test_backfill_recovers_legacy_levels_and_is_idempotent_and_report_scoped(): void
    {
        $fixture = $this->context();
        $degreeOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $specialOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $unresolvedOwner = User::factory()->create(['degree' => 'no_degrees']);
        $this->workplace($degreeOwner, $fixture['russianDepartment']);
        $this->workplace($specialOwner, $fixture['specialDepartment']);
        $this->workplace($unresolvedOwner, $fixture['russianDepartment']);

        $legacyB2 = $this->datum($degreeOwner, $fixture['criterion'], 'accepted', 1);
        $legacyC2 = $this->datum($specialOwner, $fixture['criterion'], 'accepted', 2, 'C2 sertifikat tasdiqlandi.');
        $unresolved = $this->datum($unresolvedOwner, $fixture['criterion'], 'accepted', 4);

        $otherReport = Report::query()->create(['name' => ['uz' => 'Boshqa hisobot'], 'status' => '0']);
        $otherCriterion = $this->criterion($otherReport);
        $otherDatum = $this->datum($degreeOwner, $otherCriterion, 'accepted', 1);

        $this->artisan('kpi:criteria:backfill-foreign-language-points', [
            '--report' => $fixture['report']->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutput('O‘zgartiriladigan accepted resurslar: 2')
            ->expectsOutput('Darajasi aniqlanmagan resurslar: 1')
            ->assertSuccessful();

        $this->assertSame(1.0, $legacyB2->fresh()->point);

        $this->artisan('kpi:criteria:backfill-foreign-language-points', [
            '--report' => $fixture['report']->getKey(),
        ])
            ->expectsOutput('O‘zgartirilgan accepted resurslar: 2')
            ->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-foreign-language-points', [
            '--report' => $fixture['report']->getKey(),
        ])
            ->expectsOutput('O‘zgartirilgan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(5.0, $legacyB2->fresh()->point);
        $this->assertSame(6.0, $legacyC2->fresh()->point);
        $this->assertSame(4.0, $unresolved->fresh()->point);
        $this->assertSame(1.0, $otherDatum->fresh()->point);
        $this->assertSame(1, $legacyB2->histories()
            ->where('message_type', 'foreign_language_point_recalculated')
            ->count());
    }

    public function test_foreign_faculty_only_backfill_corrects_other_departments_and_leaves_russian_department_unchanged(): void
    {
        $fixture = $this->context();
        $specialOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $russianOwner = User::factory()->create(['degree' => 'no_degrees']);
        $this->workplace($specialOwner, $fixture['specialDepartment']);
        $this->workplace($russianOwner, $fixture['russianDepartment']);

        $specialDatum = $this->datum($specialOwner, $fixture['criterion'], 'accepted', 7);
        $specialDatum->update(['manual_score_option_id' => $fixture['options']['c1']->getKey()]);
        $russianDatum = $this->datum($russianOwner, $fixture['criterion'], 'accepted', 1, 'C1 sertifikat tasdiqlandi.');

        $this->artisan('kpi:criteria:backfill-foreign-language-points', [
            '--report' => $fixture['report']->getKey(),
            '--foreign-faculty-only' => true,
        ])
            ->expectsOutput('O‘zgartirilgan accepted resurslar: 1')
            ->assertSuccessful();

        $this->assertSame(3.0, $specialDatum->fresh()->point);
        $this->assertSame(1.0, $russianDatum->fresh()->point);
        $this->assertSame($fixture['options']['c1']->getKey(), $specialDatum->fresh()->manual_score_option_id);
        $this->assertNull($russianDatum->fresh()->manual_score_option_id);
    }

    public function test_backfill_stops_without_changes_when_department_configuration_is_missing(): void
    {
        $fixture = $this->context();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $this->workplace($owner, $fixture['specialDepartment']);
        $datum = $this->datum($owner, $fixture['criterion'], 'accepted', 7, 'C1 sertifikat tasdiqlandi.');
        config()->set('kpi.foreign_language_faculty_department_id');

        $this->artisan('kpi:criteria:backfill-foreign-language-points', [
            '--report' => $fixture['report']->getKey(),
            '--foreign-faculty-only' => true,
        ])
            ->expectsOutput('Chet tillari fakulteti konfiguratsiyasi topilmadi. Productionda php artisan config:cache ni qayta ishga tushiring.')
            ->assertFailed();

        $this->assertSame(7.0, $datum->fresh()->point);
        $this->assertNull($datum->manual_score_option_id);
    }

    /** @return array<string, array{string, string, bool, float}> */
    public static function scoreMatrix(): array
    {
        return [
            'special academic B1' => ['b1', 'hold_degrees', true, 0],
            'special academic B2' => ['b2', 'hold_degrees', true, 0],
            'special academic C1' => ['c1', 'hold_degrees', true, 3],
            'special academic C2' => ['c2', 'hold_degrees', true, 6],
            'general academic B1' => ['b1', 'hold_degrees', false, 2],
            'general other B1' => ['b1', 'no_degrees', false, 3],
            'general academic B2' => ['b2', 'hold_degrees', false, 5],
            'general other B2' => ['b2', 'physical', false, 6],
            'general academic C1' => ['c1', 'hold_degrees', false, 7],
            'general other C1' => ['c1', 'foreign_lang', false, 8],
            'general academic C2' => ['c2', 'hold_degrees', false, 10],
            'general other C2' => ['c2', 'no_degrees', false, 10],
            'general lower than B1' => ['a2', 'no_degrees', false, 0],
        ];
    }

    /** @return array{report: Report, criterion: Criterion, reviewer: User, specialDepartment: Department, russianDepartment: Department, options: array<string, CriterionManualScoreOption>} */
    private function context(): array
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            Evaluation::query()->firstOrCreate(
                ['code' => $category],
                ['name' => ['uz' => $category], 'status' => '1'],
            );
        }

        $report = Report::query()->create(['name' => ['uz' => 'Faol hisobot'], 'status' => '1']);
        $criterion = $this->criterion($report);
        $reviewer = User::factory()->create();
        config()->set('kpi.accepted_ai_reviewer_hemis_id', $reviewer->hemis_id);
        CriterionReviewerAssignment::query()->create([
            'hemis_id' => $reviewer->hemis_id,
            'criterion_id' => $criterion->getKey(),
            'criterion_code' => ForeignLanguageCertificateCriterionRule::CODE,
        ]);

        $faculty = $this->department('Chet tillari');
        $specialDepartment = $this->department('Ingliz tili va adabiyoti', $faculty);
        $russianDepartment = $this->department('Rus tili va adabiyoti', $faculty);
        config()->set('kpi.foreign_language_faculty_department_id', $faculty->getKey());
        config()->set('kpi.russian_language_department_id', $russianDepartment->getKey());

        $options = [];
        foreach (ForeignLanguageCertificateCriterionRule::LEVEL_LABELS as $code => $label) {
            $options[$code] = CriterionManualScoreOption::query()->create([
                'criterion_id' => $criterion->getKey(),
                'code' => $code,
                'label' => ['uz' => $label.' sertifikat'],
                'point' => 0,
                'sort_order' => count($options) + 1,
                'active' => true,
            ]);
        }

        return compact(
            'report',
            'criterion',
            'reviewer',
            'specialDepartment',
            'russianDepartment',
            'options',
        );
    }

    private function criterion(Report $report): Criterion
    {
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Maximum],
            ['name' => ['uz' => 'Maksimal'], 'status' => '1'],
        );
        $criterion = Criterion::query()->create([
            'code' => ForeignLanguageCertificateCriterionRule::CODE,
            'name' => ['uz' => 'Xorijiy tillarni bilish'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'file_limit' => 1,
            'upload' => '1',
            'status' => '1',
        ]);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => 10,
            ]);
        }

        return $criterion;
    }

    private function department(string $name, ?Department $parent = null): Department
    {
        return Department::query()->create([
            'id' => $this->referenceId++,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
            'parent_id' => $parent?->getKey(),
        ]);
    }

    private function workplace(User $user, Department $department): Workplace
    {
        $academicDegree = AcademicDegree::query()->create(['id' => $this->referenceId++, 'name' => 'PhD']);
        $academicRank = AcademicRank::query()->create(['id' => $this->referenceId++, 'name' => 'Dotsent']);
        $form = EmploymentForm::query()->firstOrCreate(
            ['id' => EmploymentForm::PRIMARY_WORKPLACE_ID],
            ['name' => 'Asosiy ish joyi'],
        );
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => 'O‘qituvchi']);
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

    private function datum(
        User $owner,
        Criterion $criterion,
        string $status,
        float $point = 0,
        ?string $reason = null,
    ): Datum {
        return Datum::query()->create([
            'name' => 'Xorijiy til sertifikati',
            'material' => ['type' => 'file', 'path' => 'certificates/test.pdf', 'disk' => 'local'],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'reason' => $reason,
        ]);
    }
}
