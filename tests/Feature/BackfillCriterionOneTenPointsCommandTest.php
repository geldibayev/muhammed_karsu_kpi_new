<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\OptionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BackfillCriterionOneTenPointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_updates_low_accepted_points_and_recalculates_fixed_category_scores(): void
    {
        $this->seed(OptionSeeder::class);
        $maximum = Formula::query()->where('code', Formula::Maximum)->firstOrFail();
        $report = $this->createReport('Asosiy hisobot');
        $criterion = $this->createCriterion($report, $maximum);
        $otherReport = $this->createReport('Boshqa hisobot');
        $otherCriterion = $this->createCriterion($otherReport, $maximum);

        $hold = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);
        $foreign = User::factory()->create(['degree' => 'foreign_lang']);
        $physical = User::factory()->create(['degree' => 'physical']);

        $holdLow = $this->createDatum($hold, $criterion, 'accepted', 1);
        $this->createDatum($hold, $criterion, 'accepted', 2);
        $withoutDegreeLow = $this->createDatum($withoutDegree, $criterion, 'accepted', 1);
        $foreignLow = $this->createDatum($foreign, $criterion, 'accepted', 1);
        $physicalLow = $this->createDatum($physical, $criterion, 'accepted', 3);
        $physicalHigh = $this->createDatum($physical, $criterion, 'accepted', 5);
        $cancelled = $this->createDatum($foreign, $criterion, 'cancelled', 1);
        $otherReportDatum = $this->createDatum($foreign, $otherCriterion, 'accepted', 1);

        $this->artisan('kpi:criteria:backfill-1-10-points', ['report' => $report->getKey()])
            ->expectsOutput('1.10 bo‘yicha toifa balliga yangilanadigan resurslar: 4')
            ->expectsOutput('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.')
            ->assertSuccessful();
        $this->assertSame(1.0, $holdLow->fresh()->point);

        $this->artisan('kpi:criteria:backfill-1-10-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2.0, $holdLow->fresh()->point);
        $this->assertSame(2.0, $withoutDegreeLow->fresh()->point);
        $this->assertSame(3.0, $foreignLow->fresh()->point);
        $this->assertSame(4.0, $physicalLow->fresh()->point);
        $this->assertSame(5.0, $physicalHigh->fresh()->point);
        $this->assertSame(1.0, $cancelled->fresh()->point);
        $this->assertSame(1.0, $otherReportDatum->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 4);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $foreignLow->getKey(),
            'message_type' => 'criterion_1_10_point_corrected',
        ]);

        foreach ([$hold->id => 2.0, $withoutDegree->id => 2.0, $foreign->id => 3.0, $physical->id => 4.0] as $userId => $point) {
            $this->assertSame($point, Point::query()
                ->where('report_id', $report->getKey())
                ->where('criterion_id', $criterion->getKey())
                ->where('user_id', $userId)
                ->value('point'));
        }

        $this->artisan('kpi:criteria:backfill-1-10-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertDatabaseCount('datum_histories', 4);
    }

    public function test_command_rejects_unknown_report(): void
    {
        $this->artisan('kpi:criteria:backfill-1-10-points', ['report' => 999999])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();
    }

    private function createReport(string $name): Report
    {
        return Report::query()->create(['name' => ['uz' => $name], 'status' => '1']);
    }

    private function createCriterion(Report $report, Formula $formula): Criterion
    {
        $parent = Criterion::query()->create([
            'code' => '1',
            'name' => ['uz' => '1-bo‘lim'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '1.10',
            'name' => ['uz' => 'Master-klass'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'file_limit' => 1,
            'status' => '1',
        ]);

        foreach (['hold_degrees' => 2, 'no_degrees' => 2, 'foreign_lang' => 3, 'physical' => 4] as $evaluation => $score) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => $score,
            ]);
        }

        return $criterion;
    }

    private function createDatum(User $user, Criterion $criterion, string $status, float $point): Datum
    {
        return Datum::query()->create([
            'name' => 'Dalil',
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
