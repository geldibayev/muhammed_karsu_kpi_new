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

class BackfillCriterionThreeOneFifteenPointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_only_updates_eligible_low_accepted_points_and_is_idempotent(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $otherReport = Report::query()->create([
            'name' => ['uz' => 'Boshqa hisobot'],
            'status' => '0',
        ]);
        $criterion = $this->createCriterion($report, '3.1.15');
        $otherCriterion = $this->createCriterion($report, '3.1.14');
        $otherReportCriterion = $this->createCriterion($otherReport, '3.1.15');
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);
        $eligible = $this->createDatum($withDegree, $criterion, 'accepted', 0.5);
        $alreadyCorrect = $this->createDatum($withDegree, $criterion, 'accepted', 2);
        $higherPoint = $this->createDatum($withDegree, $criterion, 'accepted', 3);
        $ineligibleDegree = $this->createDatum($withoutDegree, $criterion, 'accepted', 1);
        $notAccepted = $this->createDatum($withDegree, $criterion, 'cancelled', 0);
        $otherCriterionDatum = $this->createDatum($withDegree, $otherCriterion, 'accepted', 1);
        $otherReportDatum = $this->createDatum($withDegree, $otherReportCriterion, 'accepted', 1);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->twice()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $this->artisan('kpi:criteria:backfill-3-1-15-points', ['report' => $report->getKey()])
            ->expectsOutput('3.1.15 bo‘yicha 2 ballga yangilanadigan resurslar: 1')
            ->expectsOutput('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.')
            ->assertSuccessful();
        $this->assertSame(0.5, $eligible->fresh()->point);

        $this->artisan('kpi:criteria:backfill-3-1-15-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('3.1.15 bo‘yicha 2 ballga yangilanadigan resurslar: 1')
            ->expectsOutput('3.1.15 bo‘yicha 2 ballga yangilandi: 1')
            ->assertSuccessful();

        $this->assertSame(2.0, $eligible->fresh()->point);
        $this->assertSame(2.0, $alreadyCorrect->fresh()->point);
        $this->assertSame(3.0, $higherPoint->fresh()->point);
        $this->assertSame(1.0, $ineligibleDegree->fresh()->point);
        $this->assertSame(0.0, $notAccepted->fresh()->point);
        $this->assertSame(1.0, $otherCriterionDatum->fresh()->point);
        $this->assertSame(1.0, $otherReportDatum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->getKey(),
            'user_id' => $withDegree->getKey(),
            'message_type' => 'criterion_3_1_15_point_corrected',
        ]);
        $this->assertDatabaseCount('datum_histories', 1);

        $this->artisan('kpi:criteria:backfill-3-1-15-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('3.1.15 bo‘yicha 2 ballga yangilanadigan resurslar: 0')
            ->expectsOutput('3.1.15 bo‘yicha 2 ballga yangilandi: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('datum_histories', 1);
    }

    public function test_command_rejects_an_unknown_report(): void
    {
        $this->artisan('kpi:criteria:backfill-3-1-15-points', ['report' => 999999])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();
    }

    public function test_migration_corrects_existing_points_idempotently(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $criterion = $this->createCriterion($report, '3.1.15');
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $datum = $this->createDatum($owner, $criterion, 'accepted', 1);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->twice()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $migration = require database_path('migrations/2026_08_11_152333_backfill_criterion_three_one_fifteen_points.php');
        $migration->up();
        $migration->up();

        $this->assertSame(2.0, $datum->fresh()->point);
        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'criterion_3_1_15_point_corrected')
            ->count());
    }

    private function createCriterion(Report $report, string $code): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code.' kriteriya'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
    }

    private function createDatum(
        User $user,
        Criterion $criterion,
        string $status,
        float $point,
    ): Datum {
        return Datum::query()->create([
            'name' => 'Test resursi',
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
