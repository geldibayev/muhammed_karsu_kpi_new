<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\KpiCriterionSpecification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CriterionThreeOneTenFixedScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_only_decides_status_and_server_assigns_category_point(): void
    {
        foreach ([
            'hold_degrees' => 2.0,
            'no_degrees' => 4.0,
            'foreign_lang' => 2.0,
            'physical' => 2.0,
        ] as $category => $expectedPoint) {
            $accepted = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult('accepted', 0.5, 'Ilmiy tadbirdagi ma’ruza tasdiqlandi.'),
                FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
                $category,
            );

            $this->assertSame('accepted', $accepted->status);
            $this->assertSame($expectedPoint, $accepted->point);
        }

        $cancelled = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('cancelled', 4, 'Faqat oddiy qatnashuv tasdiqlandi.'),
            FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
            'no_degrees',
        );
        $checking = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('checking', 4, 'Hujjat xira.'),
            FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
            'no_degrees',
        );

        $this->assertSame(0.0, $cancelled->point);
        $this->assertSame(0.0, $checking->point);

        $specification = KpiCriterionSpecification::criteria()[
            FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE
        ];

        $this->assertSame(KpiCriterionSpecification::Maximum, $specification['formula']);
        $this->assertSame(1, $specification['file_limit']);
        $this->assertSame(4.0, $specification['ai_submission_max_point']);
        $this->assertFalse($specification['divide_ai_point_by_authors']);
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::threeOneTenPrompt(),
            $specification['ai_prompt'],
        );
    }

    public function test_human_reviewer_only_approves_or_rejects_and_server_assigns_the_point(): void
    {
        [, $criterion] = $this->context();
        $reviewer = User::factory()->create(['hemis_id' => 3462211323]);

        foreach (['no_degrees' => 4.0, 'hold_degrees' => 2.0] as $category => $expectedPoint) {
            $owner = User::factory()->create(['degree' => $category]);
            $datum = $this->pendingDatum($criterion, $owner, $reviewer);

            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum), ['point' => 1])
                ->assertSessionHasErrors('point');
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum))
                ->assertRedirect(route('ai-human-reviews.index'));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame($expectedPoint, $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'user_id' => $reviewer->getKey(),
                'message_type' => 'manual_review_approved',
            ]);
        }

        $rejectedOwner = User::factory()->create(['degree' => 'no_degrees']);
        $rejectedDatum = $this->pendingDatum($criterion, $rejectedOwner, $reviewer, point: 4);

        $this->actingAs($reviewer)
            ->patch(route('reviews.reject', $rejectedDatum), [
                'reason' => 'Faqat oddiy qatnashuv tasdiqlangan.',
            ])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertSame('cancelled', $rejectedDatum->fresh()->status);
        $this->assertSame(0.0, $rejectedDatum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $rejectedDatum->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'manual_review_rejected',
        ]);
    }

    public function test_backfill_gives_one_category_maximum_when_user_has_multiple_old_accepted_resources(): void
    {
        [$report, $criterion] = $this->context();
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $oldData = collect([
            $this->acceptedDatum($criterion, $withoutDegree, 0.5),
            $this->acceptedDatum($criterion, $withoutDegree, 1.5),
            $this->acceptedDatum($criterion, $withoutDegree, 4),
            $this->acceptedDatum($criterion, $withDegree, 1),
        ]);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
            '--dry-run' => true,
        ])
            ->expectsOutput('Qayta hisoblanadigan accepted resurslar: 3')
            ->assertSuccessful();

        $this->assertSame([0.5, 1.5, 4.0, 1.0], $oldData
            ->map(fn (Datum $datum): float => $datum->fresh()->point)
            ->all());

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 3')
            ->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame([4.0, 4.0, 4.0, 2.0], $oldData
            ->map(fn (Datum $datum): float => $datum->fresh()->point)
            ->all());
        $this->assertSame(4.0, $this->finalPoint($report, $criterion, $withoutDegree));
        $this->assertSame(2.0, $this->finalPoint($report, $criterion, $withDegree));
        $this->assertSame(3, $oldData
            ->sum(fn (Datum $datum): int => $datum->histories()
                ->where('message_type', 'fixed_resource_point_recalculated')
                ->count()));
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
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
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
            'code' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_TEN_CODE,
            'name' => ['uz' => 'Universitet nomidan ilmiy tadbirdagi ishtirok'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'file_limit' => 1,
            'status' => '1',
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::threeOneTenPrompt(),
            'ai_submission_max_point' => 4,
        ]);

        foreach (['hold_degrees' => 2, 'no_degrees' => 4, 'foreign_lang' => 2, 'physical' => 2] as $category => $score) {
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
            'name' => 'Inson tekshiruvini kutayotgan resurs',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'point' => $point,
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
    }

    private function acceptedDatum(Criterion $criterion, User $owner, float $point): Datum
    {
        return Datum::query()->create([
            'name' => 'Eski tasdiqlangan resurs',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }

    private function finalPoint(Report $report, Criterion $criterion, User $owner): float
    {
        return (float) Point::query()
            ->whereBelongsTo($report)
            ->whereBelongsTo($criterion)
            ->whereBelongsTo($owner)
            ->value('point');
    }
}
