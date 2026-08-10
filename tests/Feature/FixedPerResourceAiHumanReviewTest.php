<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FixedPerResourceAiHumanReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_reviewer_receives_all_fixed_resource_criteria(): void
    {
        $fixture = $this->createCriteria();
        $expectedReviewers = [
            '3.1.12' => 3462111204,
            '3.1.14' => 3462011188,
            '4.1.3' => 3462211323,
            '4.1.4' => 3462211323,
            '4.1.5' => 3462211323,
        ];

        foreach ($fixture['criteria'] as $criterion) {
            $this->assertSame(
                $expectedReviewers[$criterion->code],
                AiHumanReviewAssignment::reviewerHemisIdFor($criterion),
                "{$criterion->code} mezoni noto‘g‘ri tekshiruvchiga biriktirilgan.",
            );
        }
    }

    public function test_human_approval_uses_category_rate_and_caps_the_final_total(): void
    {
        $fixture = $this->createCriteria();
        $reviewer = User::factory()->create(['hemis_id' => 3462211323]);
        $expectedRates = [
            '3.1.12' => [
                'hold_degrees' => 3.0,
                'no_degrees' => 3.0,
                'foreign_lang' => 3.0,
                'physical' => 3.0,
            ],
            '3.1.14' => [
                'hold_degrees' => 4.0,
                'no_degrees' => 1.0,
                'foreign_lang' => 1.0,
                'physical' => 1.0,
            ],
            '4.1.3' => [
                'hold_degrees' => 0.5,
                'no_degrees' => 0.75,
                'foreign_lang' => 0.25,
                'physical' => 0.75,
            ],
            '4.1.4' => [
                'hold_degrees' => 0.5,
                'no_degrees' => 0.75,
                'foreign_lang' => 0.5,
                'physical' => 1.0,
            ],
            '4.1.5' => [
                'hold_degrees' => 1.0,
                'no_degrees' => 1.0,
                'foreign_lang' => 1.0,
                'physical' => 1.0,
            ],
        ];

        foreach ($expectedRates as $criterionCode => $categoryRates) {
            foreach ($categoryRates as $category => $expectedRate) {
                $owner = User::factory()->create(['degree' => $category]);
                $datum = $this->createPendingDatum(
                    $owner,
                    $fixture['criteria'][$criterionCode],
                    $reviewer,
                );

                $this->actingAs($reviewer)
                    ->patch(route('reviews.approve', $datum), ['point' => 99])
                    ->assertSessionHasErrors('point');
                $this->actingAs($reviewer)
                    ->patch(route('reviews.approve', $datum))
                    ->assertRedirect(route('ai-human-reviews.index'));

                $this->assertSame($expectedRate, $datum->fresh()->point);
                $this->assertDatabaseHas('datum_histories', [
                    'datum_id' => $datum->getKey(),
                    'user_id' => $reviewer->getKey(),
                    'message_type' => 'manual_review_approved',
                ]);
            }
        }

        $criterion = $fixture['criteria']['4.1.5'];
        $owner = User::factory()->create(['degree' => 'no_degrees']);

        foreach (range(1, 2) as $index) {
            $datum = $this->createPendingDatum($owner, $criterion, $reviewer, "Resurs {$index}");
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum))
                ->assertRedirect(route('ai-human-reviews.index'));
            $this->assertSame(1.0, $datum->fresh()->point);
        }

        $this->assertSame(1.0, (float) Point::query()
            ->where('report_id', $fixture['report']->getKey())
            ->where('criterion_id', $criterion->getKey())
            ->where('user_id', $owner->getKey())
            ->value('point'));
    }

    public function test_backfill_command_corrects_existing_accepted_points_idempotently(): void
    {
        $fixture = $this->createCriteria();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $unlimitedFormula = Formula::query()->create([
            'code' => Formula::Unlimited,
            'name' => ['uz' => 'Cheklanmagan yig‘indi'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '3.1.11',
            'name' => ['uz' => 'Talaba stipendiatligi'],
            'parent_id' => $fixture['criteria']['3.1.12']->parent_id,
            'report_id' => $fixture['report']->getKey(),
            'formula_id' => $unlimitedFormula->getKey(),
            'checking' => 'ai',
            'file_limit' => 1,
            'upload' => '1',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 4,
        ]);
        $datum = Datum::query()->create([
            'name' => 'Eski AI tasdiqlagan resurs',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 1,
        ]);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', ['--dry-run' => true])
            ->expectsOutput('Qayta hisoblanadigan accepted resurslar: 1')
            ->assertSuccessful();
        $this->assertSame(1.0, $datum->fresh()->point);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points')->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-fixed-resource-points')
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(4.0, $datum->fresh()->point);
        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'fixed_resource_point_recalculated')
            ->count());
        $this->assertSame(4.0, (float) Point::query()
            ->where('report_id', $fixture['report']->getKey())
            ->where('criterion_id', $criterion->getKey())
            ->where('user_id', $owner->getKey())
            ->value('point'));
    }

    /**
     * @return array{report: Report, criteria: array<string, Criterion>}
     */
    private function createCriteria(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ballga asoslangan'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'code' => '4',
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);
        $maximums = [
            '3.1.12' => [3, 3, 3, 3],
            '3.1.14' => [4, 1, 1, 1],
            '4.1.3' => [2, 3, 1, 3],
            '4.1.4' => [2, 3, 2, 4],
            '4.1.5' => [2, 1, 2, 2],
        ];
        $fileLimits = ['3.1.12' => 1, '3.1.14' => 1, '4.1.3' => 4, '4.1.4' => 4, '4.1.5' => 2];
        $categories = ['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'];

        foreach ($categories as $category) {
            Evaluation::query()->create([
                'code' => $category,
                'name' => ['uz' => $category],
                'status' => '1',
            ]);
        }

        $criteria = [];
        foreach ($maximums as $criterionCode => $scores) {
            $criterion = Criterion::query()->create([
                'code' => $criterionCode,
                'name' => ['uz' => "{$criterionCode} mezoni"],
                'parent_id' => $parent->getKey(),
                'report_id' => $report->getKey(),
                'formula_id' => $formula->getKey(),
                'checking' => 'ai',
                'file_limit' => $fileLimits[$criterionCode],
                'upload' => '1',
                'status' => '1',
            ]);

            foreach (array_combine($categories, $scores) as $category => $score) {
                CriterionEvaluation::query()->create([
                    'criterion_id' => $criterion->getKey(),
                    'evaluation' => $category,
                    'has' => '1',
                    'score' => $score,
                ]);
            }

            $criteria[$criterionCode] = $criterion;
        }

        return compact('report', 'criteria');
    }

    private function createPendingDatum(
        User $owner,
        Criterion $criterion,
        User $reviewer,
        string $name = 'Tekshiriladigan resurs',
    ): Datum {
        return Datum::query()->create([
            'name' => $name,
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
    }
}
