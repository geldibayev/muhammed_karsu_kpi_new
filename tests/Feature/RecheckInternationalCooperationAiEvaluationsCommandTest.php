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

    public function test_dry_run_does_not_change_or_dispatch_the_accepted_resource(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $datum = $this->createAiEvaluatedDatum(
            $criterion,
            'accepted',
            3,
            'Al-Farabi nomidagi Qozog‘iston Milliy Universiteti bilan hamkorlik tasdiqlandi.',
        );

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--datum' => [$datum->id],
        ])
            ->expectsOutputToContain('qayta tekshiruvga mos resurslar: 1')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'criterion_2_1_6_percentage_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_recalculates_determinable_resources_and_only_queues_unknown_ones(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->createReportAndCriterion();
        $alFarabi = $this->createAiEvaluatedDatum(
            $criterion,
            'accepted',
            3,
            'Al-Farabi nomidagi Qozog‘iston Milliy Universiteti bilan hamkorlik tasdiqlandi.',
        );
        $ranked = $this->createAiEvaluatedDatum(
            $criterion,
            'accepted',
            4,
            'Universitet QS reytingida 450-o‘rinda.',
            'physical',
        );
        $unknown = $this->createAiEvaluatedDatum(
            $criterion,
            'accepted',
            3,
            'Xorijiy universitet bilan hamkorlik tasdiqlandi, reyting o‘rni yozilmagan.',
        );
        $manuallyReviewed = $this->createAiEvaluatedDatum(
            $criterion,
            'accepted',
            1,
            'Xorijlik talabalarni jalb qilganligi rasmiy hujjatda tasdiqlandi.',
        );
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
        $otherCriterionDatum = $this->createAiEvaluatedDatum($otherCriterion, 'accepted', 1);

        [$otherReport, $otherReportCriterion] = $this->createReportAndCriterion();
        $otherReportDatum = $this->createAiEvaluatedDatum($otherReportCriterion, 'accepted', 1);

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('serverda qayta hisoblandi: 3')
            ->expectsOutputToContain('AI qayta tekshiruviga qo‘yildi: 1')
            ->assertSuccessful();

        $this->assertSame('accepted', $alFarabi->fresh()->status);
        $this->assertSame(2.25, $alFarabi->fresh()->point);
        $this->assertSame('top_300', $alFarabi->fresh()->university_tier);
        $this->assertSame(2.0, $ranked->fresh()->point);
        $this->assertSame('top_500', $ranked->fresh()->university_tier);
        $this->assertSame(3.0, $manuallyReviewed->fresh()->point);
        $this->assertSame('foreign_students', $manuallyReviewed->fresh()->university_tier);

        foreach ([$alFarabi, $ranked, $manuallyReviewed] as $datum) {
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->id,
                'message_type' => 'criterion_2_1_6_server_recalculated',
            ]);
        }

        $this->assertSame('checking', $unknown->fresh()->status);
        $this->assertSame(0.0, $unknown->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $unknown->id,
            'message_type' => 'criterion_2_1_6_percentage_recheck_queued',
        ]);
        $this->assertSame('accepted', $otherCriterionDatum->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);

        $this->artisan('kpi:recheck-international-cooperation-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('qayta tekshiruvga mos resurslar: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
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

    private function createAiEvaluatedDatum(
        Criterion $criterion,
        string $status,
        float $point,
        string $reason = 'Oldingi AI xulosasi.',
        string $degree = 'hold_degrees',
    ): Datum {
        $user = User::factory()->create(['degree' => $degree]);
        $datum = Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => $status,
            'point' => $point,
            'reason' => $reason,
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
                'message' => $reason,
                'message_type' => 'ai_evaluation',
            ],
        ]);

        return $datum;
    }
}
