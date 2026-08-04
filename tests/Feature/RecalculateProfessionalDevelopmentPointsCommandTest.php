<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Support\ProfessionalDevelopmentCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecalculateProfessionalDevelopmentPointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_dry_runs_and_applies_only_old_seventy_and_twenty_percent_points(): void
    {
        [$report, $criterion] = $this->createReportAndCriterion();
        $degreeTwo = User::factory()->create(['degree' => 'hold_degrees']);
        $degreeThree = User::factory()->create(['degree' => 'no_degrees']);
        $oldSeventyOfTwo = $this->createDatum(
            $degreeTwo,
            $criterion,
            1.4,
            'Universitet Top-300. Ball: 2 * 70% = 1.4. Manba: https://example.com',
        );
        $oldSeventyOfThree = $this->createDatum(
            $degreeThree,
            $criterion,
            2.1,
            'Top-300 talikka kiradi, 3 ballning 70% ulushi - 2.1 ball berildi.',
        );
        $oldTwentyOfTwo = $this->createDatum(
            $degreeTwo,
            $criterion,
            0.4,
            'Top-1000 talikka kiradi, 2 ballning 20% ulushi - 0.4 ball berildi.',
        );
        $oldTwentyOfThree = $this->createDatum(
            $degreeThree,
            $criterion,
            0.6,
            'Top-1000 talikka kiradi, 3 ballning 20% ulushi - 0.6 ball berildi.',
        );
        $unchangedTopFiveHundred = $this->createDatum($degreeTwo, $criterion, 1.0, 'Top-500.');
        $nonStandardLegacyPoint = $this->createDatum($degreeThree, $criterion, 1.0, 'Top-500.');
        $alreadyCorrect = $this->createDatum($degreeTwo, $criterion, 1.5, 'Top-300 — 75%.', 'top_300');
        $cancelled = $this->createDatum($degreeTwo, $criterion, 1.4, 'Top-300.', status: 'cancelled');

        $otherReport = Report::query()->create(['name' => ['uz' => 'Boshqa'], 'status' => '1']);
        $otherCriterion = $this->createCriterion($otherReport, ProfessionalDevelopmentCriterionRule::CODE);
        $otherReportDatum = $this->createDatum($degreeTwo, $otherCriterion, 1.4, 'Top-300.');
        $otherCodeCriterion = $this->createCriterion($report, '2.1.6');
        $otherCodeDatum = $this->createDatum($degreeTwo, $otherCodeCriterion, 1.4, 'Top-300.');

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $this->artisan('kpi:criteria:recalculate-2-1-5-points', ['report' => $report->getKey()])
            ->expectsOutput('old_70_percent: 2')
            ->expectsOutput('old_20_percent: 2')
            ->expectsOutput('Bazaga o‘zgarish yozilmadi. Qo‘llash uchun --apply parametridan foydalaning.')
            ->assertSuccessful();
        $this->assertSame(1.4, $oldSeventyOfTwo->fresh()->point);

        $this->artisan('kpi:criteria:recalculate-2-1-5-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertCorrected($oldSeventyOfTwo, 1.5, 'top_300', '75%', '70%');
        $this->assertCorrected($oldSeventyOfThree, 2.25, 'top_300', '75%', '70%');
        $this->assertCorrected($oldTwentyOfTwo, 0.5, 'top_1000', '25%', '20%');
        $this->assertCorrected($oldTwentyOfThree, 0.75, 'top_1000', '25%', '20%');
        $this->assertSame(1.0, $unchangedTopFiveHundred->fresh()->point);
        $this->assertSame(1.0, $nonStandardLegacyPoint->fresh()->point);
        $this->assertSame(1.5, $alreadyCorrect->fresh()->point);
        $this->assertSame(1.4, $cancelled->fresh()->point);
        $this->assertSame(1.4, $otherReportDatum->fresh()->point);
        $this->assertSame(1.4, $otherCodeDatum->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 4);

        $this->artisan('kpi:criteria:recalculate-2-1-5-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertDatabaseCount('datum_histories', 4);
    }

    public function test_conflicting_structured_tier_prevents_all_updates(): void
    {
        [$report, $criterion] = $this->createReportAndCriterion();
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        $valid = $this->createDatum($user, $criterion, 1.4, 'Top-300.');
        $conflict = $this->createDatum($user, $criterion, 1.4, 'Top-300.', 'top_500');
        $this->mock(RecalculateReportPoints::class)
            ->shouldNotReceive('handle');

        $this->artisan('kpi:criteria:recalculate-2-1-5-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain((string) $conflict->getKey())
            ->assertFailed();

        $this->assertSame(1.4, $valid->fresh()->point);
        $this->assertSame(1.4, $conflict->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    public function test_command_rejects_invalid_unknown_and_report_without_criterion(): void
    {
        $this->artisan('kpi:criteria:recalculate-2-1-5-points', ['report' => 'x'])
            ->expectsOutput('Hisobot ID musbat butun son bo‘lishi kerak.')
            ->assertFailed();
        $this->artisan('kpi:criteria:recalculate-2-1-5-points', ['report' => 999999])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();

        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $this->artisan('kpi:criteria:recalculate-2-1-5-points', ['report' => $report->getKey()])
            ->expectsOutput('Tanlangan hisobotda 2.1.5 kriteriyasi topilmadi.')
            ->assertFailed();
    }

    /** @return array{Report, Criterion} */
    private function createReportAndCriterion(): array
    {
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $criterion = $this->createCriterion($report, ProfessionalDevelopmentCriterionRule::CODE);

        foreach ([
            'hold_degrees' => 2,
            'foreign_lang' => 2,
            'no_degrees' => 3,
            'physical' => 3,
        ] as $evaluation => $score) {
            Evaluation::query()->firstOrCreate(
                ['code' => $evaluation],
                ['name' => ['uz' => $evaluation]],
            );
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => $score,
            ]);
        }

        return [$report, $criterion];
    }

    private function createCriterion(Report $report, string $code): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'status' => '1',
        ]);
    }

    private function createDatum(
        User $user,
        Criterion $criterion,
        float $point,
        string $reason,
        ?string $universityTier = null,
        string $status = 'accepted',
    ): Datum {
        return Datum::query()->create([
            'name' => 'Test resursi',
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'reason' => $reason,
            'university_tier' => $universityTier,
        ]);
    }

    private function assertCorrected(
        Datum $datum,
        float $point,
        string $tier,
        string $presentInReason,
        string $missingFromReason,
    ): void {
        $datum->refresh();
        $this->assertSame($point, $datum->point);
        $this->assertSame($tier, $datum->university_tier);
        $this->assertStringContainsString($presentInReason, $datum->reason);
        $this->assertStringNotContainsString($missingFromReason, $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'criterion_2_1_5_point_recalculated',
        ]);
    }
}
