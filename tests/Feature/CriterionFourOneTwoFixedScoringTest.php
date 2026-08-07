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

class CriterionFourOneTwoFixedScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_only_decides_status_and_server_assigns_category_point(): void
    {
        foreach ([
            'hold_degrees' => 1.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 2.0,
        ] as $category => $expectedPoint) {
            $accepted = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult('accepted', 99, 'Talablar tasdiqlandi.'),
                FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
                $category,
            );

            $this->assertSame('accepted', $accepted->status);
            $this->assertSame($expectedPoint, $accepted->point);
        }

        $cancelled = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('cancelled', 99, 'Murojaat xususiy tashkilotdan.'),
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
            'physical',
        );
        $checking = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('checking', 99, 'Hujjat xira.'),
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
            'physical',
        );

        $this->assertSame(0.0, $cancelled->point);
        $this->assertSame(0.0, $checking->point);

        $specification = KpiCriterionSpecification::criteria()[
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE
        ];

        $this->assertSame(KpiCriterionSpecification::Maximum, $specification['formula']);
        $this->assertSame(1, $specification['file_limit']);
        $this->assertSame(2.0, $specification['ai_submission_max_point']);
        $this->assertFalse($specification['divide_ai_point_by_authors']);
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::fourOneTwoPrompt(),
            $specification['ai_prompt'],
        );
    }

    public function test_human_reviewer_only_approves_and_server_assigns_the_point(): void
    {
        [, $criterion] = $this->context();
        $reviewer = User::factory()->create(['hemis_id' => 3462211323]);

        foreach (['no_degrees' => 1.0, 'physical' => 2.0] as $category => $expectedPoint) {
            $owner = User::factory()->create(['degree' => $category]);
            $datum = Datum::query()->create([
                'name' => 'Inson tekshiruvini kutayotgan resurs',
                'user_id' => $owner->getKey(),
                'criterion_id' => $criterion->getKey(),
                'status' => 'checking',
                'reviewer_hemis_id' => $reviewer->hemis_id,
            ]);

            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum), ['point' => 99])
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

        $rejectedOwner = User::factory()->create(['degree' => 'physical']);
        $rejectedDatum = Datum::query()->create([
            'name' => 'Rad etiladigan resurs',
            'user_id' => $rejectedOwner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'point' => 2,
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->patch(route('reviews.reject', $rejectedDatum), [
                'reason' => 'Davlat organining rasmiy murojaati tasdiqlanmadi.',
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

    public function test_backfill_corrects_old_accepted_points_and_final_totals_idempotently(): void
    {
        [$report, $criterion] = $this->context();
        $regularOwner = User::factory()->create(['degree' => 'no_degrees']);
        $physicalOwner = User::factory()->create(['degree' => 'physical']);
        $regularDatum = $this->acceptedDatum($criterion, $regularOwner, 0.25);
        $physicalDatum = $this->acceptedDatum($criterion, $physicalOwner, 1);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
            '--dry-run' => true,
        ])
            ->expectsOutput('Qayta hisoblanadigan accepted resurslar: 2')
            ->assertSuccessful();

        $this->assertSame(0.25, $regularDatum->fresh()->point);
        $this->assertSame(1.0, $physicalDatum->fresh()->point);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 2')
            ->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(1.0, $regularDatum->fresh()->point);
        $this->assertSame(2.0, $physicalDatum->fresh()->point);
        $this->assertSame(1.0, $this->finalPoint($report, $criterion, $regularOwner));
        $this->assertSame(2.0, $this->finalPoint($report, $criterion, $physicalOwner));
        $this->assertSame(1, $regularDatum->histories()
            ->where('message_type', 'fixed_resource_point_recalculated')
            ->count());
    }

    public function test_backfill_rebuilds_formula_totals_when_raw_points_are_already_correct(): void
    {
        [$report, $criterion] = $this->context();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $this->acceptedDatum($criterion, $owner, 1);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(1.0, $this->finalPoint($report, $criterion, $owner));
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
            'code' => '4',
            'name' => ['uz' => 'Boshqa faoliyat'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE,
            'name' => ['uz' => 'Ilmiy-amaliy taklif'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'file_limit' => 1,
            'status' => '1',
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneTwoPrompt(),
            'ai_submission_max_point' => 2,
        ]);

        foreach (['hold_degrees' => 1, 'no_degrees' => 1, 'foreign_lang' => 1, 'physical' => 2] as $category => $score) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => $score,
            ]);
        }

        return [$report, $criterion];
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
