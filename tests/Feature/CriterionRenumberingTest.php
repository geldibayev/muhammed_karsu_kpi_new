<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use App\Models\Report;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CriterionRenumberingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_renumbers_the_active_report_without_changing_ids_or_historical_reports(): void
    {
        $historicalReport = $this->createReport('historical', '0');
        $activeReport = $this->createReport('active', '1');
        $historicalCriterion = $this->createCriterion($historicalReport, '1.7', 7);
        $criteria = collect(['1.5', '1.6', '1.7', '1.8', '1.9', '1.10'])
            ->mapWithKeys(function (string $code) use ($activeReport): array {
                $criterion = $this->createCriterion(
                    $activeReport,
                    $code,
                    (int) str($code)->afterLast('.')->toString(),
                );
                CriterionReviewerAssignment::query()->create([
                    'criterion_id' => $criterion->getKey(),
                    'criterion_code' => $code,
                    'hemis_id' => 1000 + $criterion->getKey(),
                ]);

                return [$code => $criterion];
            });

        $migration = require database_path('migrations/2026_08_17_164615_renumber_first_section_criteria_after_retirements.php');
        $migration->up();

        foreach ([
            '1.5' => 'retired.1.5',
            '1.6' => 'retired.1.6',
            '1.7' => '1.5',
            '1.8' => '1.6',
            '1.9' => '1.7',
            '1.10' => '1.8',
        ] as $oldCode => $newCode) {
            $criterion = $criteria->get($oldCode)->fresh();

            $this->assertSame($newCode, $criterion->code);
            $this->assertDatabaseHas('criterion_reviewer_assignments', [
                'criterion_id' => $criterion->getKey(),
                'criterion_code' => $newCode,
            ]);

            if (is_numeric($newCode)) {
                $this->assertSame((int) str($newCode)->afterLast('.')->toString(), $criterion->sort_order);
            }
        }

        $this->assertSame('1.7', $historicalCriterion->fresh()->code);

        $migration->down();

        foreach ($criteria as $oldCode => $criterion) {
            $this->assertSame($oldCode, $criterion->fresh()->code);
            $this->assertDatabaseHas('criterion_reviewer_assignments', [
                'criterion_id' => $criterion->getKey(),
                'criterion_code' => $oldCode,
            ]);
        }
    }

    private function createReport(string $code, string $status): Report
    {
        return Report::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'status' => $status,
        ]);
    }

    private function createCriterion(Report $report, string $code, int $sortOrder): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'report_id' => $report->getKey(),
            'sort_order' => $sortOrder,
            'upload' => '0',
            'status' => '0',
        ]);
    }
}
