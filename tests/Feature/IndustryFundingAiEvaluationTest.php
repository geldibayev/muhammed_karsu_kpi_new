<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Services\IndustryFundingScoreCalculator;
use App\Support\IndustryFundingCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndustryFundingAiEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prompt_requires_a_contract_and_the_uploader_in_its_participant_list(): void
    {
        $this->assertStringContainsString('shartnomaning o‘zi bo‘lishi shart', IndustryFundingCriterionRule::PROMPT);
        $this->assertStringContainsString('`author_full_name` tizim bergan ishonchli resurs yuklovchisining ismidir', IndustryFundingCriterionRule::PROMPT);
        $this->assertStringContainsString('`author_full_name` shartnomadagi loyiha rahbari, ijrochilar yoki ishtirokchilar ro‘yxatida bo‘lmasa cancelled', IndustryFundingCriterionRule::PROMPT);
    }

    public function test_server_calculates_each_million_then_divides_by_coauthors(): void
    {
        $aiResult = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'received_amount' => 12_500_000.50,
            'author_count' => 5,
            'resource_date' => '2026-07-15',
            'reason' => 'To‘lov universitet hisobiga tushgan.',
        ], 999_999.99);

        $result = (new IndustryFundingScoreCalculator)->apply($aiResult);

        $this->assertSame(2.5, $result->point);
        $this->assertSame(12_500_000.50, $result->receivedAmount);
        $this->assertSame(5, $result->authorCount);
        $this->assertStringContainsString('12500000.50 so‘m / 1 000 000 / 5', $result->reason);
    }

    public function test_recheck_command_is_dry_run_by_default_and_apply_is_idempotent(): void
    {
        Queue::fake();
        $report = Report::query()->create([
            'name' => ['uz' => 'Joriy hisobot'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => IndustryFundingCriterionRule::CODE,
            'name' => ['uz' => 'Xo‘jalik shartnomasi'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => IndustryFundingCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        $user = User::factory()->create();
        $datum = Datum::query()->create([
            'name' => 'To‘lov hujjati',
            'material' => ['type' => 'file', 'path' => 'funding.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => 1,
            'author_count' => 2,
            'reason' => 'Eski AI xulosasi.',
        ]);
        $datum->histories()->create([
            'user_id' => $user->id,
            'type' => 'success',
            'message' => 'Eski AI xulosasi.',
            'message_type' => 'ai_evaluation',
        ]);
        $datum->histories()->create([
            'user_id' => $user->id,
            'type' => 'success',
            'message' => 'Inson tekshiruvchi tasdiqlagan.',
            'message_type' => 'manual_review_approved',
        ]);

        $this->artisan('kpi:recheck-industry-funding-ai-evaluations', [
            'report' => $report->id,
        ])->expectsOutputToContain('Dry-run')->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        Queue::assertNothingPushed();

        $this->artisan('kpi:recheck-industry-funding-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('AI qayta tekshiruviga qo‘yildi: 1')->assertSuccessful();

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertNull($datum->author_count);
        $this->assertNull($datum->received_amount);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);

        $this->artisan('kpi:recheck-industry-funding-ai-evaluations', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('qayta tekshiruvga mos resurslar: 0')->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
    }

    public function test_human_fallback_also_calculates_amount_divided_by_coauthors_on_server(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011188]);
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        AiHumanReviewAssignment::query()->create([
            'hemis_id' => $reviewer->hemis_id,
            'active_slot' => 1,
            'assigned_at' => now(),
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Joriy hisobot'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => IndustryFundingCriterionRule::CODE,
            'name' => ['uz' => 'Xo‘jalik shartnomasi'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => IndustryFundingCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 5,
        ]);
        $datum = Datum::query()->create([
            'name' => 'To‘lov hujjati',
            'material' => ['type' => 'file', 'path' => 'funding.pdf'],
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('name="received_amount"', false)
            ->assertSee('name="author_count"', false)
            ->assertDontSee('name="point"', false);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum))
            ->assertSessionHasErrors(['received_amount', 'author_count']);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), [
                'received_amount' => 9_000_000,
                'author_count' => 3,
            ])
            ->assertRedirect(route('ai-human-reviews.index'));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(3.0, $datum->point);
        $this->assertSame(3, $datum->author_count);
        $this->assertSame('9000000.00', $datum->received_amount);

        $superAdmin = User::factory()->create(['rol' => ['super_admin']]);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('id="updated-received-amount"', false)
            ->assertSee('id="updated-industry-author-count"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $datum), [
                'received_amount' => 12_000_000,
                'author_count' => 4,
                'point' => 5,
                'score_change_reason' => 'Summa va hammualliflar qayta tekshirildi.',
            ])
            ->assertSessionHasErrors('point');
        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $datum), [
                'received_amount' => 12_000_000,
                'author_count' => 4,
                'score_change_reason' => 'Summa va hammualliflar qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $datum));

        $datum->refresh();
        $this->assertSame(3.0, $datum->point);
        $this->assertSame(4, $datum->author_count);
        $this->assertSame('12000000.00', $datum->received_amount);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), [
                'reason' => 'Qayta tekshiruv talab qilindi.',
            ])
            ->assertRedirect(route('upload.details', $datum));
        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('id="cancelled-received-amount"', false)
            ->assertSee('id="cancelled-industry-author-count"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                'received_amount' => 15_000_000,
                'author_count' => 5,
                'point' => 5,
            ])
            ->assertSessionHasErrors('point');
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                'received_amount' => 15_000_000,
                'author_count' => 5,
            ])
            ->assertRedirect(route('upload.details', $datum));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(3.0, $datum->point);
        $this->assertSame(5, $datum->author_count);
        $this->assertSame('15000000.00', $datum->received_amount);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'human_override_approved',
        ]);
    }
}
