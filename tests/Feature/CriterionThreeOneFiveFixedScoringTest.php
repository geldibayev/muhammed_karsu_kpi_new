<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class CriterionThreeOneFiveFixedScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_assigns_category_points_to_accepted_resources_in_the_selected_report(): void
    {
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $otherReport = Report::query()->create(['name' => ['uz' => 'Boshqa hisobot'], 'status' => '0']);
        $criterion = $this->createCriterion($report, '3.1.5');
        $otherCriterion = $this->createCriterion($report, '3.1.6');
        $otherReportCriterion = $this->createCriterion($otherReport, '3.1.5');
        $accepted = collect([
            $this->createDatum($criterion, 'hold_degrees', 'accepted', 5),
            $this->createDatum($criterion, 'no_degrees', 'accepted', 0),
            $this->createDatum($criterion, 'foreign_lang', 'accepted', 1),
            $this->createDatum($criterion, 'physical', 'accepted', 4),
        ]);
        $alreadyCorrect = $this->createDatum($criterion, 'hold_degrees', 'accepted', 2);
        $cancelled = $this->createDatum($criterion, 'hold_degrees', 'cancelled', 5);
        $wrongCriterion = $this->createDatum($otherCriterion, 'hold_degrees', 'accepted', 5);
        $wrongReport = $this->createDatum($otherReportCriterion, 'hold_degrees', 'accepted', 5);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->twice()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $arguments = ['--criterion' => '3.1.5', '--report' => $report->getKey()];

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            ...$arguments,
            '--dry-run' => true,
        ])
            ->expectsOutput('Qayta hisoblanadigan accepted resurslar: 4')
            ->assertSuccessful();

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', $arguments)
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 4')
            ->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', $arguments)
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame([2.0, 3.0, 3.0, 3.0], $accepted
            ->map(fn (Datum $datum): float => $datum->fresh()->point)
            ->all());
        $this->assertSame(2.0, $alreadyCorrect->fresh()->point);
        $this->assertSame(5.0, $cancelled->fresh()->point);
        $this->assertSame(5.0, $wrongCriterion->fresh()->point);
        $this->assertSame(5.0, $wrongReport->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 4);
    }

    public function test_command_rejects_invalid_and_unknown_reports(): void
    {
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => '3.1.5',
            '--report' => 'x',
        ])
            ->expectsOutput('Hisobot ID musbat butun son bo‘lishi kerak.')
            ->assertFailed();

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => '3.1.5',
            '--report' => 999999,
        ])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();
    }

    private function createCriterion(Report $report, string $code): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
    }

    private function createDatum(
        Criterion $criterion,
        string $degree,
        string $status,
        float $point,
    ): Datum {
        $user = User::factory()->create(['degree' => $degree]);

        return Datum::query()->create([
            'name' => 'Test resursi',
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
