<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionPoint;
use App\Models\CriterionReviewerAssignment;
use App\Models\Evaluation;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RetiredCriteriaScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_migration_retires_unused_criteria_and_updates_existing_one_seven_awards(): void
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            Evaluation::query()->create([
                'code' => $evaluation,
                'name' => ['uz' => $evaluation],
                'status' => '1',
            ]);
        }

        $oldReport = $this->createReport('old-report', '0');
        $activeReport = $this->createReport('active-report', '1');
        $oldCriterion = $this->createCriterion($oldReport, '1.7');
        $retiredCriteria = collect(['1.5', '1.6'])->map(
            fn (string $code): Criterion => $this->createCriterion($activeReport, $code),
        );
        $criterionOneSeven = $this->createCriterion($activeReport, '1.7');
        $user = User::factory()->create();

        foreach ($retiredCriteria as $index => $criterion) {
            CriterionReviewerAssignment::query()->create([
                'criterion_id' => $criterion->getKey(),
                'criterion_code' => $criterion->code,
                'hemis_id' => 1000 + $index,
            ]);
        }

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterionOneSeven->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => 2,
            ]);
        }

        CriterionPoint::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterionOneSeven->getKey(),
            'report_id' => $activeReport->getKey(),
            'point' => 2,
            'files' => 1,
        ]);
        Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterionOneSeven->getKey(),
            'report_id' => $activeReport->getKey(),
            'point' => 2,
        ]);
        Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $oldCriterion->getKey(),
            'report_id' => $oldReport->getKey(),
            'point' => 2,
        ]);

        $migration = require database_path('migrations/2026_08_07_122828_retire_criteria_1_5_and_1_6_and_raise_criterion_1_7_score.php');
        $migration->up();
        $migration->up();

        foreach ($retiredCriteria as $criterion) {
            $this->assertSame('0', $criterion->fresh()->status);
            $this->assertSame('0', $criterion->fresh()->upload);
            $this->assertDatabaseMissing('criterion_reviewer_assignments', [
                'criterion_id' => $criterion->getKey(),
            ]);
        }

        $this->assertSame(4, CriterionEvaluation::query()
            ->where('criterion_id', $criterionOneSeven->getKey())
            ->where('has', '1')
            ->where('score', 10)
            ->count());
        $this->assertDatabaseHas('criterion_points', [
            'report_id' => $activeReport->getKey(),
            'criterion_id' => $criterionOneSeven->getKey(),
            'user_id' => $user->getKey(),
            'point' => 10,
        ]);
        $this->assertDatabaseHas('points', [
            'report_id' => $activeReport->getKey(),
            'criterion_id' => $criterionOneSeven->getKey(),
            'user_id' => $user->getKey(),
            'point' => 10,
        ]);
        $this->assertDatabaseHas('points', [
            'report_id' => $oldReport->getKey(),
            'criterion_id' => $oldCriterion->getKey(),
            'user_id' => $user->getKey(),
            'point' => 2,
        ]);
    }

    private function createReport(string $code, string $status): Report
    {
        return Report::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'status' => $status,
        ]);
    }

    private function createCriterion(Report $report, string $code): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code.' mezoni'],
            'report_id' => $report->getKey(),
            'upload' => '0',
            'status' => '1',
        ]);
    }
}
