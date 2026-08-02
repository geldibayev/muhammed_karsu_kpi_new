<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use App\Support\InternationalCooperationCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckInternationalCooperationAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_does_not_change_or_dispatch_the_cancelled_resource(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $datum = $this->createAiEvaluatedDatum($criterion, 'cancelled', 0);

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--datum' => [$datum->id],
        ])
            ->expectsOutputToContain('qayta tekshiruvga mos resurslar: 1')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('cancelled', $datum->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_international_cooperation_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_requeues_only_target_criterion_ai_decisions_and_is_idempotent(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $cancelled = $this->createAiEvaluatedDatum($criterion, 'cancelled', 0);
        $accepted = $this->createAiEvaluatedDatum($criterion, 'accepted', 3);
        $manuallyReviewed = $this->createAiEvaluatedDatum($criterion, 'accepted', 3);
        $manuallyReviewed->histories()->create([
            'user_id' => $manuallyReviewed->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);

        $otherCriterion = Criterion::query()->create([
            'code' => '3.1.2',
            'name' => ['uz' => 'Boshqa AI mezoni'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => 'Boshqa prompt.',
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        $otherCriterionDatum = $this->createAiEvaluatedDatum($otherCriterion, 'cancelled', 0);

        [$otherReport, $otherReportCriterion] = $this->createReportAndCriterion();
        $otherReportDatum = $this->createAiEvaluatedDatum($otherReportCriterion, 'cancelled', 0);

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('AI qayta tekshiruviga qo‘yildi: 2')
            ->assertSuccessful();

        foreach ([$cancelled, $accepted] as $datum) {
            $this->assertSame('checking', $datum->fresh()->status);
            $this->assertSame(0.0, $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->id,
                'message_type' => 'ai_international_cooperation_recheck_queued',
            ]);
        }

        $this->assertSame('accepted', $manuallyReviewed->fresh()->status);
        $this->assertSame('cancelled', $otherCriterionDatum->fresh()->status);
        $this->assertSame('cancelled', $otherReportDatum->fresh()->status);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('qayta tekshiruvga mos resurslar: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
        $this->assertSame($otherReport->id, $otherReportDatum->criterion->report_id);
    }

    /** @return array{Report, Criterion} */
    private function createReportAndCriterion(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => InternationalCooperationCriterionRule::CODE,
            'name' => ['uz' => 'Xalqaro hamkorlik'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => InternationalCooperationCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'file_limit' => 1,
            'res_type' => 'file',
            'upload' => '1',
            'status' => '1',
        ]);

        return [$report, $criterion];
    }

    private function createAiEvaluatedDatum(Criterion $criterion, string $status, float $point): Datum
    {
        $user = User::factory()->create();
        $datum = Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => $status,
            'point' => $point,
            'reason' => 'Oldingi AI xulosasi.',
        ]);
        $datum->histories()->createMany([
            [
                'user_id' => $user->id,
                'type' => 'info',
                'message' => 'Resurs foydalanuvchi tomonidan yuborildi.',
                'message_type' => 'submission_created',
            ],
            [
                'user_id' => $user->id,
                'type' => $status === 'accepted' ? 'success' : 'error',
                'message' => 'Oldingi AI xulosasi.',
                'message_type' => 'ai_evaluation',
            ],
        ]);

        return $datum;
    }
}
