<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RecheckCriterionFourOneOneAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_does_not_change_or_dispatch_resources(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $datum = $this->datum($criterion, 'accepted', 0.75);
        $this->mock(RecalculateReportPoints::class)->shouldNotReceive('handle');

        $this->artisan('kpi:criteria:recheck-4-1-1-resources', [
            'report' => $report->getKey(),
        ])
            ->expectsOutputToContain('qayta tekshiriladigan resurslar: 1')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(0.75, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'ai_four_one_one_reference_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_requeues_every_non_deleted_target_resource_and_is_idempotent(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $targets = collect([
            $this->datum($criterion, 'received', 0.75),
            $this->datum($criterion, 'checking', 0.75),
            $this->datum($criterion, 'accepted', 0.75),
            $this->datum($criterion, 'cancelled', 0),
        ]);
        $targets[2]->histories()->create([
            'user_id' => $targets[2]->user_id,
            'type' => 'success',
            'message' => 'Inson tasdiqlagan.',
            'message_type' => 'manual_review_approved',
        ]);
        $deleted = $this->datum($criterion, 'deleted', 0.75);
        $otherCriterion = Criterion::query()->create([
            'code' => '4.1.2',
            'name' => ['uz' => 'Boshqa mezon'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'status' => '1',
        ]);
        $otherCriterionDatum = $this->datum($otherCriterion, 'accepted', 1);
        [, $otherReportCriterion] = $this->context();
        $otherReportDatum = $this->datum($otherReportCriterion, 'accepted', 0.75);

        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(fn (Report $actual): bool => $actual->is($report)));
        $this->app->instance(RecalculateReportPoints::class, $recalculateReportPoints);

        $this->artisan('kpi:criteria:recheck-4-1-1-resources', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('Checking holatiga o‘tkazildi: 4')
            ->assertSuccessful();

        foreach ($targets as $datum) {
            $this->assertSame('checking', $datum->fresh()->status);
            $this->assertSame(0.0, $datum->fresh()->point);
            $this->assertNull($datum->fresh()->reviewer_hemis_id);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'ai_four_one_one_reference_recheck_queued',
                'message' => 'Ma’lumotnoma qabul etilmaydi. Resurs 4.1.1 mezoni bo‘yicha qayta AI tekshiruviga yuborildi.',
            ]);
        }

        $this->assertSame('deleted', $deleted->fresh()->status);
        $this->assertSame('accepted', $otherCriterionDatum->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 4);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $targets->pluck('id')->contains($job->datumId),
        );

        $this->artisan('kpi:criteria:recheck-4-1-1-resources', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('qayta tekshiriladigan resurslar: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 4);
    }

    public function test_migration_updates_existing_four_one_one_prompt_idempotently(): void
    {
        [, $criterion] = $this->context();
        $criterion->update(['ai_prompt' => 'Eski prompt.']);
        $migration = require database_path('migrations/2026_08_19_115731_update_four_one_one_ai_prompt_for_reference_rejection.php');

        $migration->up();
        $migration->up();

        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            $criterion->fresh()->ai_prompt,
        );
    }

    public function test_invalid_report_or_limit_fails_without_dispatching(): void
    {
        Queue::fake();
        [$report] = $this->context();

        $this->artisan('kpi:criteria:recheck-4-1-1-resources', [
            'report' => 999_999,
        ])->assertFailed();
        $this->artisan('kpi:criteria:recheck-4-1-1-resources', [
            'report' => $report->getKey(),
            '--limit' => 0,
        ])->assertFailed();

        Queue::assertNothingPushed();
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
            'name' => ['uz' => 'OAV yoki ijtimoiy tarmoqlardagi chiqish'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);

        return [$report, $criterion];
    }

    private function datum(Criterion $criterion, string $status, float $point): Datum
    {
        $owner = User::factory()->create();

        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'reason' => 'Oldingi xulosa.',
            'reviewer_hemis_id' => $owner->hemis_id,
        ]);
    }
}
