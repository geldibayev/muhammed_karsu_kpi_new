<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use App\Support\ProfessionalDevelopmentCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RecheckProfessionalDevelopmentPointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_candidates_without_changing_or_dispatching_them(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $datum = $this->createAiEvaluatedDatum($criterion, 1.5);

        $this->artisan('kpi:criteria:recheck-2-1-5-points', ['report' => $report->getKey()])
            ->expectsOutput('2.1.5 bo‘yicha eski formatdagi AI resurslari: 1')
            ->expectsOutput('Dry-run: 1 ta resurs qayta tekshiruvga tushadi. O‘zgarish kiritilmadi.')
            ->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(1.5, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'criterion_2_1_5_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_only_requeues_legacy_ai_decisions_and_is_idempotent(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $eligible = $this->createAiEvaluatedDatum($criterion, 1.5);
        $alreadyCorrect = $this->createAiEvaluatedDatum($criterion, 2.25, 'top_300');
        $manuallyReviewed = $this->createAiEvaluatedDatum($criterion, 1.5);
        $manuallyReviewed->histories()->create([
            'user_id' => $manuallyReviewed->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);
        $withoutAiHistory = $this->createDatum($criterion, 1.5);

        $otherCriterion = Criterion::query()->create([
            'code' => '2.1.6',
            'name' => ['uz' => 'Boshqa kriteriya'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'ai_prompt' => 'Tekshiring.',
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        $otherCriterionDatum = $this->createAiEvaluatedDatum($otherCriterion, 1.5);
        [$otherReport, $otherReportCriterion] = $this->createReportAndCriterion();
        $otherReportDatum = $this->createAiEvaluatedDatum($otherReportCriterion, 1.5);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $this->artisan('kpi:criteria:recheck-2-1-5-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('2.1.5 bo‘yicha eski formatdagi AI resurslari: 1')
            ->expectsOutput('2.1.5 bo‘yicha AI qayta tekshiruviga qo‘yildi: 1')
            ->assertSuccessful();

        $eligible->refresh();
        $this->assertSame('checking', $eligible->status);
        $this->assertSame(0.0, $eligible->point);
        $this->assertNull($eligible->university_tier);
        $this->assertSame(Datum::PUBLIC_CHECKING_REASON, $eligible->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->getKey(),
            'message_type' => 'criterion_2_1_5_recheck_queued',
        ]);
        $this->assertSame('accepted', $alreadyCorrect->fresh()->status);
        $this->assertSame('accepted', $manuallyReviewed->fresh()->status);
        $this->assertSame('accepted', $withoutAiHistory->fresh()->status);
        $this->assertSame('accepted', $otherCriterionDatum->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        $this->assertNotSame($report->getKey(), $otherReport->getKey());
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $eligible->getKey()
                && $job->criterionId === $criterion->getKey(),
        );

        $this->artisan('kpi:criteria:recheck-2-1-5-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutput('2.1.5 bo‘yicha eski formatdagi AI resurslari: 0')
            ->expectsOutput('2.1.5 bo‘yicha AI qayta tekshiruviga qo‘yildi: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
        $this->assertSame(1, $eligible->histories()
            ->where('message_type', 'criterion_2_1_5_recheck_queued')
            ->count());
    }

    public function test_limit_is_validated_and_applied_to_the_dry_run_count(): void
    {
        [$report, $criterion] = $this->createReportAndCriterion();
        $this->createAiEvaluatedDatum($criterion, 1.5);
        $this->createAiEvaluatedDatum($criterion, 1.0);

        $this->artisan('kpi:criteria:recheck-2-1-5-points', [
            'report' => $report->getKey(),
            '--limit' => 1,
        ])
            ->expectsOutput('2.1.5 bo‘yicha eski formatdagi AI resurslari: 2')
            ->expectsOutput('Dry-run: 1 ta resurs qayta tekshiruvga tushadi. O‘zgarish kiritilmadi.')
            ->assertSuccessful();

        $this->artisan('kpi:criteria:recheck-2-1-5-points', [
            'report' => $report->getKey(),
            '--limit' => 0,
        ])
            ->expectsOutput('--limit musbat butun son bo‘lishi kerak.')
            ->assertFailed();
    }

    public function test_command_rejects_invalid_and_unknown_reports(): void
    {
        $this->artisan('kpi:criteria:recheck-2-1-5-points', ['report' => 'x'])
            ->expectsOutput('Hisobot ID musbat butun son bo‘lishi kerak.')
            ->assertFailed();

        $this->artisan('kpi:criteria:recheck-2-1-5-points', ['report' => 999999])
            ->expectsOutput('Hisobot topilmadi: 999999.')
            ->assertFailed();
    }

    /** @return array{Report, Criterion} */
    private function createReportAndCriterion(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => ProfessionalDevelopmentCriterionRule::CODE,
            'name' => ['uz' => 'Xorijda malaka oshirish'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'ai_prompt' => ProfessionalDevelopmentCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'file_limit' => 1,
            'res_type' => 'file',
            'upload' => '1',
            'status' => '1',
        ]);

        return [$report, $criterion];
    }

    private function createAiEvaluatedDatum(
        Criterion $criterion,
        float $point,
        ?string $universityTier = null,
    ): Datum {
        $datum = $this->createDatum($criterion, $point, $universityTier);
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'success',
            'message' => 'Oldingi AI xulosasi.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }

    private function createDatum(
        Criterion $criterion,
        float $point,
        ?string $universityTier = null,
    ): Datum {
        $user = User::factory()->create();

        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
            'university_tier' => $universityTier,
            'reason' => 'Oldingi xulosa.',
        ]);
    }
}
