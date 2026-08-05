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

        foreach (array_values($options) as $index => $option) {
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $this->createDatum($owner, $criterion, "Videodars {$index}")), [
                    'score_option_id' => $option->getKey(),
                ])
                ->assertRedirect(route('reviews.index'));
        }

        $this->assertSame(6.0, Datum::query()
            ->whereBelongsTo($owner)
            ->whereBelongsTo($criterion)
            ->sum('point'));
        $this->assertSame(6.0, (float) Point::query()
            ->whereBelongsTo($owner)
            ->whereBelongsTo($criterion)
            ->value('point'));
    }

    public function test_review_only_shows_resource_types_not_yet_accepted_for_the_user(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);

        $first = $this->createDatum($owner, $criterion, 'Birinchi');
        $this->actingAs($reviewer)->get(route('reviews.show', $first))
            ->assertSee('Videodars')->assertSee('Videorolik')->assertSee('Taqdimot');
        $this->actingAs($reviewer)->patch(route('reviews.approve', $first), [
            'score_option_id' => $options['video_lesson']->getKey(),
        ]);

        $second = $this->createDatum($owner, $criterion, 'Ikkinchi');
        $this->actingAs($reviewer)->get(route('reviews.show', $second))
            ->assertDontSee('50% = 3.00 ball')
            ->assertSee('40% = 2.40 ball')
            ->assertSee('10% = 0.60 ball');
        $this->actingAs($reviewer)->patch(route('reviews.approve', $second), [
            'score_option_id' => $options['video_clip']->getKey(),
        ]);

        $third = $this->createDatum($owner, $criterion, 'Uchinchi');
        $this->actingAs($reviewer)->get(route('reviews.show', $third))
            ->assertDontSee('50% = 3.00 ball')
            ->assertDontSee('40% = 2.40 ball')
            ->assertSee('10% = 0.60 ball')
            ->assertSee('name="score_option_id"', false);
    }

    public function test_same_resource_type_cannot_be_approved_twice_for_one_user(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);
        $first = $this->createDatum($owner, $criterion, 'Birinchi');
        $second = $this->createDatum($owner, $criterion, 'Ikkinchi');

        $this->actingAs($reviewer)->patch(route('reviews.approve', $first), [
            'score_option_id' => $options['video_lesson']->getKey(),
        ]);
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $second))
            ->patch(route('reviews.approve', $second), [
                'score_option_id' => $options['video_lesson']->getKey(),
            ])
            ->assertRedirect(route('reviews.show', $second))
            ->assertSessionHasErrors('score_option_id');

        $this->assertSame('received', $second->fresh()->status);
    }

    public function test_assigned_reviewer_can_move_legacy_duplicate_to_an_unused_type(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1.1',
        ]);
        $first = $this->createDatum($owner, $criterion, 'Eski birinchi');
        $second = $this->createDatum($owner, $criterion, 'Eski takror');
        foreach ([$first, $second] as $datum) {
            $datum->update([
                'status' => 'accepted',
                'point' => 3,
                'manual_score_option_id' => $options['video_lesson']->getKey(),
            ]);
        }

        $this->actingAs($reviewer)->get(route('upload.details', $second))
            ->assertOk()
            ->assertSee('Takrorlangan toifa')
            ->assertSee('Videorolik')
            ->assertSee('Taqdimot');
        $this->actingAs($reviewer)
            ->patch(route('upload.educational-content-type.update', $second), [
                'score_option_id' => $options['video_clip']->getKey(),
            ])
            ->assertRedirect(route('upload.details', $second));

        $this->assertSame($options['video_clip']->getKey(), $second->fresh()->manual_score_option_id);
        $this->assertSame(2.4, $second->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $second->getKey(),
            'message_type' => 'criterion_1_1_resource_type_changed',
        ]);

        $this->actingAs($reviewer)
            ->from(route('upload.details', $second))
            ->patch(route('upload.educational-content-type.update', $second), [
                'score_option_id' => $options['video_lesson']->getKey(),
            ])
            ->assertSessionHasErrors('score_option_id');
    }

    public function test_cancelled_resource_reapproval_uses_an_unused_type_and_automatic_point(): void
    {
        [$criterion, $options] = $this->criterionFixture();
        $reviewer = User::factory()->create();
        config()->set('kpi.accepted_ai_reviewer_hemis_id', $reviewer->hemis_id);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $accepted = $this->createDatum($owner, $criterion, 'Tasdiqlangan');
        $accepted->update([
            'status' => 'accepted',
            'point' => 3,
            'manual_score_option_id' => $options['video_lesson']->getKey(),
        ]);
        $cancelled = $this->createDatum($owner, $criterion, 'Qaytarilgan');
        $cancelled->update(['status' => 'cancelled']);

        $this->actingAs($reviewer)->get(route('upload.details', $cancelled))
            ->assertOk()
            ->assertDontSee('50%')
            ->assertSee('40%')
            ->assertSee('10%');
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), [
                'score_option_id' => $options['video_clip']->getKey(),
            ])
            ->assertRedirect(route('upload.details', $cancelled));

        $this->assertSame('accepted', $cancelled->fresh()->status);
        $this->assertSame(2.4, $cancelled->fresh()->point);
        $this->assertSame($options['video_clip']->getKey(), $cancelled->fresh()->manual_score_option_id);
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
