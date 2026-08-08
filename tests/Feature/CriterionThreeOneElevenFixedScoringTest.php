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

class CriterionThreeOneElevenFixedScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_is_a_four_file_primary_indicator_with_server_assigned_category_points(): void
    {
        foreach ([
            'hold_degrees' => 3.0,
            'no_degrees' => 4.0,
            'foreign_lang' => 4.0,
            'physical' => 4.0,
        ] as $category => $expectedPoint) {
            $result = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult('accepted', 99, 'Talaba yutug‘i va rahbarlik tasdiqlandi.'),
                FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE,
                $category,
            );

            $this->assertSame('accepted', $result->status);
            $this->assertSame($expectedPoint, $result->point);
        }

        $specification = KpiCriterionSpecification::criteria()[
            FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE
        ];

        $this->assertTrue((new Criterion([
            'code' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE,
        ]))->isPrimaryIndicator());
        $this->assertSame(KpiCriterionSpecification::Unlimited, $specification['formula']);
        $this->assertSame(4, $specification['file_limit']);
        $this->assertSame(4.0, $specification['ai_submission_max_point']);
        $this->assertFalse($specification['divide_ai_point_by_authors']);
    }

    public function test_each_accepted_resource_receives_the_category_point_and_totals_are_summed(): void
    {
        [$report, $criterion] = $this->context();

        foreach (['hold_degrees' => 6.0, 'no_degrees' => 8.0] as $category => $expectedTotal) {
            $owner = User::factory()->create(['degree' => $category]);

            foreach (range(1, 2) as $index) {
                Datum::query()->create([
                    'name' => "Eski tasdiqlangan resurs {$index}",
                    'user_id' => $owner->getKey(),
                    'criterion_id' => $criterion->getKey(),
                    'status' => 'accepted',
                    'point' => 1,
                ]);
            }

            $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
                '--criterion' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE,
            ])->assertSuccessful();

            $expectedResourcePoint = $category === 'hold_degrees' ? 3.0 : 4.0;
            $this->assertSame(
                [$expectedResourcePoint, $expectedResourcePoint],
                Datum::query()
                    ->whereBelongsTo($owner)
                    ->whereBelongsTo($criterion)
                    ->orderBy('id')
                    ->pluck('point')
                    ->map(fn (mixed $point): float => (float) $point)
                    ->all(),
            );
            $this->assertSame($expectedTotal, (float) Point::query()
                ->whereBelongsTo($report)
                ->whereBelongsTo($criterion)
                ->whereBelongsTo($owner)
                ->value('point'));
        }
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
            'name' => ['uz' => 'Cheklanmagan yig‘indi'],
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
            'code' => FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE,
            'name' => ['uz' => 'Talaba olimpiada va tanlov yutuqlari'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'file_limit' => 4,
            'status' => '1',
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
}
