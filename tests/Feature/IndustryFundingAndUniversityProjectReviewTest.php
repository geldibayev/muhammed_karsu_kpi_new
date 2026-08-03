<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IndustryFundingAndUniversityProjectReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_both_criteria_use_the_requested_human_reviewer(): void
    {
        foreach (['3.1.13', '3.1.14'] as $criterionCode) {
            $criterion = new Criterion(['code' => $criterionCode]);

            $this->assertSame(
                3462011188,
                AiHumanReviewAssignment::reviewerHemisIdFor($criterion),
            );
        }
    }

    public function test_assignment_command_reassigns_existing_human_reviews_for_both_criteria(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011188]);
        $oldReviewer = User::factory()->create();
        $owner = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Joriy KPI hisoboti'],
            'status' => '1',
        ]);

        foreach (['3.1.13', '3.1.14'] as $criterionCode) {
            $criterion = Criterion::query()->create([
                'code' => $criterionCode,
                'name' => ['uz' => $criterionCode.' mezoni'],
                'report_id' => $report->getKey(),
                'checking' => 'ai',
                'upload' => '1',
                'status' => '1',
            ]);
            $datum = Datum::query()->create([
                'name' => $criterionCode.' inson tekshiruvi',
                'user_id' => $owner->getKey(),
                'criterion_id' => $criterion->getKey(),
                'status' => 'checking',
                'reviewer_hemis_id' => $oldReviewer->hemis_id,
            ]);
            $datum->histories()->create([
                'user_id' => $owner->getKey(),
                'type' => 'warning',
                'message' => 'AI inson tekshiruviga yubordi.',
                'message_type' => 'ai_evaluation',
            ]);

            $this->artisan('kpi:ai:assign-human-reviews', [
                '--criterion' => $criterionCode,
                '--reassign' => true,
            ])->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 1')
                ->assertSuccessful();

            $this->assertSame($reviewer->hemis_id, $datum->fresh()->reviewer_hemis_id);
            $this->assertSame(1, $datum->histories()
                ->where('message_type', 'ai_human_review_assigned')
                ->count());
        }
    }

    public function test_university_project_human_approval_uses_fixed_category_point(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011188]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Joriy KPI hisoboti'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ball'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '3.1.14',
            'name' => ['uz' => 'Universitet tomonidan bajarilayotgan loyiha'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'file_limit' => 1,
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::threeOneFourteenPrompt(),
            'upload' => '1',
            'status' => '1',
        ]);
        $expectedPoints = [
            'hold_degrees' => 4.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 1.0,
        ];

        foreach ($expectedPoints as $evaluationCategory => $expectedPoint) {
            Evaluation::query()->create([
                'code' => $evaluationCategory,
                'name' => ['uz' => $evaluationCategory],
                'status' => '1',
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluationCategory,
                'has' => '1',
                'score' => $expectedPoint,
            ]);
            $owner = User::factory()->create(['degree' => $evaluationCategory]);
            $datum = Datum::query()->create([
                'name' => $evaluationCategory.' loyiha hujjati',
                'user_id' => $owner->getKey(),
                'criterion_id' => $criterion->getKey(),
                'status' => 'checking',
                'reviewer_hemis_id' => $reviewer->hemis_id,
            ]);

            $this->actingAs($reviewer)
                ->get(route('reviews.show', $datum))
                ->assertOk()
                ->assertDontSee('name="point"', false);
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum), ['point' => 1])
                ->assertSessionHasErrors('point');
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum))
                ->assertRedirect(route('ai-human-reviews.index'));

            $this->assertSame($expectedPoint, $datum->fresh()->point);
        }
    }
}
