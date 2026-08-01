<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckAcceptedAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_does_not_change_or_dispatch_candidates(): void
    {
        Queue::fake();
        [$report, $datum] = $this->createAcceptedAiDatum();

        $this->artisan('kpi:recheck-accepted-ai-evaluations', ['report' => $report->id])
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(5.0, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_report_period_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_requeues_only_target_report_ai_decisions_and_is_idempotent(): void
    {
        Queue::fake();
        [$report, $eligible] = $this->createAcceptedAiDatum();
        CriterionPoint::query()->create([
            'user_id' => $eligible->user_id,
            'criterion_id' => $eligible->criterion_id,
            'report_id' => $report->id,
            'point' => 5,
            'files' => 1,
        ]);
        Point::query()->create([
            'user_id' => $eligible->user_id,
            'criterion_id' => $eligible->criterion_id,
            'report_id' => $report->id,
            'point' => 5,
        ]);
        [, $manuallyReviewed] = $this->createAcceptedAiDatum($report);
        $manuallyReviewed->histories()->create([
            'user_id' => $manuallyReviewed->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);
        [, $otherReportDatum] = $this->createAcceptedAiDatum();

        $this->artisan('kpi:recheck-accepted-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('checking', $eligible->fresh()->status);
        $this->assertSame(0.0, $eligible->fresh()->point);
        $this->assertSame('accepted', $manuallyReviewed->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->id,
            'message_type' => 'ai_report_period_recheck_queued',
        ]);
        $this->assertDatabaseMissing('criterion_points', [
            'report_id' => $report->id,
            'user_id' => $eligible->user_id,
            'criterion_id' => $eligible->criterion_id,
        ]);
        $this->assertDatabaseMissing('points', [
            'report_id' => $report->id,
            'user_id' => $eligible->user_id,
            'criterion_id' => $eligible->criterion_id,
        ]);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);

        $this->artisan('kpi:recheck-accepted-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
    }

    /** @return array{Report, Datum} */
    private function createAcceptedAiDatum(?Report $report = null): array
    {
        $report ??= Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => 'Tekshiring.',
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        $user = User::factory()->create();
        $datum = Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => 'proof.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => 5,
        ]);
        $datum->histories()->create([
            'user_id' => $user->id,
            'type' => 'success',
            'message' => 'AI tasdiqladi.',
            'message_type' => 'ai_evaluation',
        ]);

        return [$report, $datum];
    }
}
