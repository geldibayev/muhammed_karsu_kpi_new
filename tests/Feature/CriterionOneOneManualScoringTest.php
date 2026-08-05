<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CriterionOneOneManualScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_approval_calculates_resource_percentage_from_user_category_maximum(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);
        $expectedPoints = [
            'hold_degrees' => ['video_lesson' => 1.5, 'video_clip' => 1.2, 'presentation' => 0.3],
            'no_degrees' => ['video_lesson' => 3.0, 'video_clip' => 2.4, 'presentation' => 0.6],
            'foreign_lang' => ['video_lesson' => 2.0, 'video_clip' => 1.6, 'presentation' => 0.4],
            'physical' => ['video_lesson' => 2.0, 'video_clip' => 1.6, 'presentation' => 0.4],
        ];

        foreach ($expectedPoints as $category => $resourcePoints) {
            foreach ($resourcePoints as $resourceType => $expectedPoint) {
                $owner = User::factory()->create(['degree' => $category]);
                $datum = $this->createDatum($owner, $criterion);

                $this->actingAs($reviewer)
                    ->patch(route('reviews.approve', $datum), [
                        'score_option_id' => $options[$resourceType]->getKey(),
                    ])
                    ->assertRedirect(route('reviews.index'));

                $datum->refresh();
                $this->assertSame('accepted', $datum->status);
                $this->assertSame($expectedPoint, $datum->point);
                $this->assertSame($options[$resourceType]->getKey(), $datum->manual_score_option_id);
            }
        }
    }

    public function test_review_interface_shows_category_maximum_percentages_and_calculated_points(): void
    {
        [$criterion] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $this->createDatum($owner, $criterion)))
            ->assertOk()
            ->assertSee('Ilmiy darajasiz')
            ->assertSee('Maksimal ball: 6.00')
            ->assertSee('ko‘pi bilan 3 ta resurs')
            ->assertSee('50% = 3.00 ball')
            ->assertSee('40% = 2.40 ball')
            ->assertSee('10% = 0.60 ball');
    }

    public function test_maximum_formula_caps_three_approved_resources_at_category_maximum(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);

        foreach (range(1, 3) as $index) {
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $this->createDatum($owner, $criterion, "Videodars {$index}")), [
                    'score_option_id' => $options['video_lesson']->getKey(),
                ])
                ->assertRedirect(route('reviews.index'));
        }

        $this->assertSame(9.0, Datum::query()
            ->whereBelongsTo($owner)
            ->whereBelongsTo($criterion)
            ->sum('point'));
        $this->assertSame(6.0, (float) Point::query()
            ->whereBelongsTo($owner)
            ->whereBelongsTo($criterion)
            ->value('point'));
    }

    public function test_report_recalculation_uses_stored_resource_type_after_user_category_changes(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $options['video_lesson']->getKey(),
            ])
            ->assertRedirect(route('reviews.index'));
        $this->assertSame(3.0, $datum->fresh()->point);

        $owner->update(['degree' => 'hold_degrees']);
        $this->app->make(RecalculateReportPoints::class)->handle($criterion->report);

        $this->assertSame(1.5, $datum->fresh()->point);
        $this->assertSame(1.5, (float) Point::query()
            ->whereBelongsTo($owner)
            ->whereBelongsTo($criterion)
            ->value('point'));
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'criterion_1_1_point_recalculated',
        ]);
    }

    public function test_unsupported_manual_option_cannot_bypass_criterion_one_one_formula(): void
    {
        [$criterion] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);
        $unsupportedOption = CriterionManualScoreOption::query()->create([
            'criterion_id' => $criterion->getKey(),
            'code' => 'unsupported',
            'label' => ['uz' => 'Noma’lum variant'],
            'point' => 99,
            'active' => true,
        ]);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $unsupportedOption->getKey(),
            ])
            ->assertRedirect(route('reviews.show', $datum))
            ->assertSessionHasErrors('score_option_id');

        $this->assertSame('received', $datum->fresh()->status);
        $this->assertSame(0.0, $datum->fresh()->point);
        $this->assertNull($datum->fresh()->manual_score_option_id);
    }

    /** @return array{Criterion, array<string, CriterionManualScoreOption>} */
    private function criterionFixture(): array
    {
        $report = Report::query()->create(['name' => ['uz' => 'Faol hisobot'], 'status' => '1']);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ball'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Asosiy bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
        ]);
        $criterion = Criterion::query()->create([
            'code' => '1.1',
            'name' => ['uz' => 'Sifatli o‘quv kontentlari'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'file_limit' => 3,
            'upload' => '1',
            'status' => '1',
        ]);

        foreach (['hold_degrees' => 3, 'no_degrees' => 6, 'foreign_lang' => 4, 'physical' => 4] as $category => $score) {
            Evaluation::query()->firstOrCreate(
                ['code' => $category],
                ['name' => ['uz' => $category], 'status' => '1'],
            );
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => $score,
            ]);
        }

        $options = collect([
            'video_lesson' => ['Videodars', 1.5],
            'video_clip' => ['Videorolik', 1.0],
            'presentation' => ['Taqdimot', 0.5],
        ])->mapWithKeys(function (array $attributes, string $code) use ($criterion): array {
            $option = CriterionManualScoreOption::query()->create([
                'criterion_id' => $criterion->getKey(),
                'code' => $code,
                'label' => ['uz' => $attributes[0]],
                'point' => $attributes[1],
                'sort_order' => 1,
                'active' => true,
            ]);

            return [$code => $option];
        })->all();

        return [$criterion, $options];
    }

    private function createDatum(User $owner, Criterion $criterion, string $name = 'Test resursi'): Datum
    {
        return Datum::query()->create([
            'name' => $name,
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'received',
        ]);
    }
}
