<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use UnexpectedValueException;

class ReportPointRecalculationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_super_admin_can_rebuild_report_points(): void
    {
        $report = $this->createReport();
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->post(route('reports.points.rebuild', $report))
            ->assertForbidden();
    }

    public function test_rebuild_calculates_all_formulas_idempotently_and_isolates_reports(): void
    {
        Evaluation::query()->create([
            'code' => 'test_degree',
            'name' => ['uz' => 'Test daraja'],
            'status' => '1',
        ]);

        $report = $this->createReport();
        $otherReport = $this->createReport('Boshqa hisobot');
        $rootCriterion = $this->createCriterion($report);
        $otherRootCriterion = $this->createCriterion($otherReport);
        $formulaOne = Formula::query()->create(['name' => ['uz' => 'Raqobat'], 'status' => '1']);
        $formulaTwo = Formula::query()->create(['name' => ['uz' => 'Cheklangan'], 'status' => '1']);
        $formulaThree = Formula::query()->create(['name' => ['uz' => 'Cheklanmagan'], 'status' => '1']);
        $proportionalCriterion = $this->createCriterion($report, $rootCriterion, $formulaOne);
        $cappedCriterion = $this->createCriterion($report, $rootCriterion, $formulaTwo);
        $unlimitedCriterion = $this->createCriterion($report, $rootCriterion, $formulaThree);
        $otherCriterion = $this->createCriterion($otherReport, $otherRootCriterion, $formulaThree);
        $firstTeacher = User::factory()->create(['degree' => 'test_degree']);
        $secondTeacher = User::factory()->create(['degree' => 'test_degree']);
        $superAdmin = User::factory()->superAdmin()->create();

        CriterionEvaluation::query()->create([
            'criterion_id' => $proportionalCriterion->id,
            'evaluation' => 'test_degree',
            'score' => 10,
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $cappedCriterion->id,
            'evaluation' => 'test_degree',
            'score' => 6,
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $unlimitedCriterion->id,
            'evaluation' => 'test_degree',
            'score' => 1,
        ]);

        $this->createDatum($firstTeacher, $proportionalCriterion, 1);
        $this->createDatum($firstTeacher, $proportionalCriterion, 3);
        $this->createDatum($secondTeacher, $proportionalCriterion, 8);
        $this->createDatum($firstTeacher, $proportionalCriterion, 100, 'checking');
        $this->createDatum($firstTeacher, $cappedCriterion, 8);
        $this->createDatum($firstTeacher, $unlimitedCriterion, 3.5);

        Point::query()->create([
            'user_id' => $firstTeacher->id,
            'criterion_id' => $otherCriterion->id,
            'report_id' => $otherReport->id,
            'point' => 77,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('reports.points.rebuild', $report))
            ->assertRedirect();

        $this->assertPointEquals($firstTeacher, $proportionalCriterion, 5);
        $this->assertPointEquals($secondTeacher, $proportionalCriterion, 10);
        $this->assertPointEquals($firstTeacher, $cappedCriterion, 6);
        $this->assertPointEquals($firstTeacher, $unlimitedCriterion, 3.5);
        $this->assertDatabaseHas('criterion_points', [
            'user_id' => $firstTeacher->id,
            'criterion_id' => $proportionalCriterion->id,
            'report_id' => $report->id,
            'point' => 4,
            'files' => 2,
        ]);
        $this->assertDatabaseHas('points', [
            'report_id' => $otherReport->id,
            'point' => 77,
        ]);

        $this->actingAs($superAdmin)->post(route('reports.points.rebuild', $report));

        $this->assertSame(4, Point::query()->where('report_id', $report->id)->count());
        $this->assertSame(4, CriterionPoint::query()->where('report_id', $report->id)->count());
        $this->assertPointEquals($firstTeacher, $proportionalCriterion, 5);
    }

    public function test_unknown_formula_rolls_back_the_report_rebuild(): void
    {
        $report = $this->createReport();
        $rootCriterion = $this->createCriterion($report);
        $criterionWithUnknownFormula = $this->createCriterion($report, $rootCriterion);
        $teacher = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->createDatum($teacher, $criterionWithUnknownFormula, 10);
        Point::query()->create([
            'user_id' => $teacher->id,
            'criterion_id' => $criterionWithUnknownFormula->id,
            'report_id' => $report->id,
            'point' => 42,
        ]);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($superAdmin)->post(route('reports.points.rebuild', $report));
            $this->fail('Unknown scoring formula did not stop the rebuild.');
        } catch (UnexpectedValueException) {
            $this->assertDatabaseHas('points', [
                'report_id' => $report->id,
                'criterion_id' => $criterionWithUnknownFormula->id,
                'point' => 42,
            ]);
            $this->assertDatabaseCount('criterion_points', 0);
        }
    }

    private function createReport(string $name = 'Test hisoboti'): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => '1',
        ]);
    }

    private function createCriterion(
        Report $report,
        ?Criterion $parent = null,
        ?Formula $formula = null,
    ): Criterion {
        return Criterion::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'parent_id' => $parent?->id,
            'report_id' => $report->id,
            'formula_id' => $formula?->id,
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createDatum(
        User $user,
        Criterion $criterion,
        float $point,
        string $status = 'accepted',
    ): Datum {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'url', 'link' => 'https://example.com'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => $status,
            'point' => $point,
        ]);
    }

    private function assertPointEquals(User $user, Criterion $criterion, float $expected): void
    {
        $actual = Point::query()
            ->where('user_id', $user->id)
            ->where('criterion_id', $criterion->id)
            ->value('point');

        $this->assertNotNull($actual);
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.0001);
    }
}
