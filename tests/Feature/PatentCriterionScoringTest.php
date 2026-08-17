<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Services\AiResourceDatePolicy;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\KpiCriterionSpecification;
use App\Support\PatentCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PatentCriterionScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_only_decides_status_and_server_assigns_patent_point_without_author_division(): void
    {
        foreach ([
            'hold_degrees' => 3.0,
            'no_degrees' => 4.0,
            'foreign_lang' => 4.0,
            'physical' => 4.0,
        ] as $category => $expectedPoint) {
            $accepted = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult(
                    'accepted',
                    0.25,
                    'Patent va mualliflik tasdiqlandi.',
                    authorCount: 12,
                    resourceDate: '2026-01-15',
                ),
                PatentCriterionRule::CODE,
                $category,
            );

            $this->assertSame('accepted', $accepted->status);
            $this->assertSame($expectedPoint, $accepted->point);
            $this->assertNull($accepted->authorCount);
        }

        $cancelled = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('cancelled', 4, 'DGU patent emas.'),
            PatentCriterionRule::CODE,
            'no_degrees',
        );
        $checking = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('checking', 4, 'Mualliflar ro‘yxati xira.'),
            PatentCriterionRule::CODE,
            'no_degrees',
        );

        $this->assertSame(0.0, $cancelled->point);
        $this->assertSame(0.0, $checking->point);

        $specification = KpiCriterionSpecification::criteria()[PatentCriterionRule::CODE];

        $this->assertSame(KpiCriterionSpecification::Unlimited, $specification['formula']);
        $this->assertSame(4, $specification['file_limit']);
        $this->assertSame(4.0, $specification['ai_submission_max_point']);
        $this->assertFalse($specification['divide_ai_point_by_authors']);
        $this->assertSame(PatentCriterionRule::PROMPT, $specification['ai_prompt']);
        $this->assertStringContainsString('DGU', PatentCriterionRule::PROMPT);
        $this->assertStringContainsString('author_full_name', PatentCriterionRule::PROMPT);
        $this->assertStringContainsString('resource_date', PatentCriterionRule::PROMPT);
        $this->assertStringContainsString('Author_count qaytarmang', PatentCriterionRule::PROMPT);
        $this->assertStringContainsString('Huquq egasi universitet yoki universitet o‘qituvchisi bo‘lishi kerak. Tasdiqlangan har bir patent', PatentCriterionRule::DESCRIPTION_UZ);
    }

    public function test_server_date_policy_rejects_out_of_period_patent_and_requires_a_clear_date(): void
    {
        [, $criterion] = $this->context();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $datum = Datum::query()->create([
            'name' => 'Patent',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
        ]);
        $policy = $this->app->make(AiResourceDatePolicy::class);

        $outsidePeriod = $policy->enforce(
            $datum,
            new AiEvaluationResult('accepted', 0, 'Patent tasdiqlandi.', resourceDate: '2024-08-31'),
        );
        $missingDate = $policy->enforce(
            $datum,
            new AiEvaluationResult('accepted', 0, 'Patent tasdiqlandi.'),
        );

        $this->assertSame('cancelled', $outsidePeriod->status);
        $this->assertSame(0.0, $outsidePeriod->point);
        $this->assertSame('checking', $missingDate->status);
        $this->assertSame(0.0, $missingDate->point);
    }

    public function test_human_reviewer_only_approves_or_rejects_and_server_assigns_patent_point(): void
    {
        [, $criterion] = $this->context();
        $reviewer = User::factory()->create(['hemis_id' => 3462011207]);

        foreach (['hold_degrees' => 3.0, 'no_degrees' => 4.0] as $category => $expectedPoint) {
            $owner = User::factory()->create(['degree' => $category]);
            $datum = $this->pendingDatum($criterion, $owner, $reviewer);

            $this->actingAs($reviewer)
                ->get(route('reviews.show', $datum))
                ->assertOk()
                ->assertDontSee('name="author_count"', false)
                ->assertDontSee('name="point"', false);
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum), ['author_count' => 2])
                ->assertSessionHasErrors('author_count');
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum))
                ->assertRedirect(route('ai-human-reviews.index'));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame($expectedPoint, $datum->fresh()->point);
            $this->assertNull($datum->fresh()->author_count);
        }

        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $rejected = $this->pendingDatum($criterion, $owner, $reviewer, point: 4);

        $this->actingAs($reviewer)
            ->patch(route('reviews.reject', $rejected), ['reason' => 'DGU patent hisoblanmaydi.'])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertSame('cancelled', $rejected->fresh()->status);
        $this->assertSame(0.0, $rejected->fresh()->point);
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            Evaluation::query()->create([
                'code' => $category,
                'name' => ['uz' => $category],
                'status' => '1',
            ]);
        }

        $formula = Formula::query()->create([
            'code' => Formula::Unlimited,
            'name' => ['uz' => 'Cheklanmagan'],
            'status' => '1',
        ]);
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $parent = Criterion::query()->create([
            'code' => '3',
            'name' => ['uz' => 'Ilmiy faoliyat'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => PatentCriterionRule::CODE,
            'name' => ['uz' => 'Patent'],
            'desc' => PatentCriterionRule::descriptions(),
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'file_limit' => 4,
            'status' => '1',
            'ai_prompt' => PatentCriterionRule::PROMPT,
            'ai_submission_max_point' => 4,
            'divide_ai_point_by_authors' => false,
        ]);

        foreach (['hold_degrees' => 3, 'no_degrees' => 4, 'foreign_lang' => 4, 'physical' => 4] as $category => $score) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => $score,
            ]);
        }

        return [$report, $criterion];
    }

    private function pendingDatum(
        Criterion $criterion,
        User $owner,
        User $reviewer,
        float $point = 0,
    ): Datum {
        return Datum::query()->create([
            'name' => 'Patent',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'point' => $point,
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
    }
}
