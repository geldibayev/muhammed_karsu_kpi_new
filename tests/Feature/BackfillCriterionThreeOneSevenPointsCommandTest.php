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

class BackfillCriterionThreeOneSevenPointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_only_updates_low_accepted_points_for_degree_holders_and_is_idempotent(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $otherReport = Report::query()->create([
            'name' => ['uz' => 'Boshqa hisobot'],
            'status' => '0',
        ]);
        $criterion = $this->createCriterion($report, '3.1.7');
        $otherCriterion = $this->createCriterion($report, '3.1.8');
        $otherReportCriterion = $this->createCriterion($otherReport, '3.1.7');
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);
        $eligible = $this->createDatum($withDegree, $criterion, 'accepted', 1);
        $zeroPoint = $this->createDatum($withDegree, $criterion, 'accepted', 0);
        $alreadyCorrect = $this->createDatum($withDegree, $criterion, 'accepted', 3);
        $higherPoint = $this->createDatum($withDegree, $criterion, 'accepted', 4);
        $ineligibleDegree = $this->createDatum($withoutDegree, $criterion, 'accepted', 1);
        $notAccepted = $this->createDatum($withDegree, $criterion, 'cancelled', 1);
        $otherCriterionDatum = $this->createDatum($withDegree, $otherCriterion, 'accepted', 1);
        $otherReportDatum = $this->createDatum($withDegree, $otherReportCriterion, 'accepted', 1);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->twice()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $this->artisan('kpi:criteria:backfill-3-1-7-points', ['report' => $report->getKey()])
            ->expectsOutput('3.1.7 bo‘yicha 3 ballga yangilanadigan resurslar: 2')
            ->expectsOutput('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.')
            ->assertSuccessful();
        $this->assertSame(1.0, $eligible->fresh()->point);

        $this->artisan('kpi:criteria:backfill-3-1-7-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('3.1.7 bo‘yicha 3 ballga yangilanadigan resurslar: 2')
            ->expectsOutput('3.1.7 bo‘yicha 3 ballga yangilandi: 2')
            ->assertSuccessful();

        $this->assertSame(3.0, $eligible->fresh()->point);
        $this->assertSame(3.0, $zeroPoint->fresh()->point);
        $this->assertSame(3.0, $alreadyCorrect->fresh()->point);
        $this->assertSame(4.0, $higherPoint->fresh()->point);
        $this->assertSame(1.0, $ineligibleDegree->fresh()->point);
        $this->assertSame(1.0, $notAccepted->fresh()->point);
        $this->assertSame(1.0, $otherCriterionDatum->fresh()->point);
        $this->assertSame(1.0, $otherReportDatum->fresh()->point);
        $this->assertSame(2, Datum::query()
            ->whereIn('id', [$eligible->getKey(), $zeroPoint->getKey()])
            ->whereHas('histories', fn ($query) => $query
                ->where('message_type', 'criterion_3_1_7_point_corrected'))
            ->count());

        $this->artisan('kpi:criteria:backfill-3-1-7-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('3.1.7 bo‘yicha 3 ballga yangilanadigan resurslar: 0')
            ->expectsOutput('3.1.7 bo‘yicha 3 ballga yangilandi: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('datum_histories', 2);
    }

    public function test_command_rejects_invalid_and_unknown_reports(): void
    {
        $this->artisan('kpi:criteria:backfill-3-1-7-points', ['report' => 'x'])
            ->expectsOutput('Hisobot ID musbat butun son bo‘lishi kerak.')
            ->assertFailed();

        $this->artisan('kpi:criteria:backfill-3-1-7-points', ['report' => 999999])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();
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
