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

class CriterionTwoOneTwoFixedScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_server_replaces_ai_point_with_two_for_every_eligible_category(): void
    {
        foreach (['hold_degrees', 'no_degrees', 'physical'] as $category) {
            $result = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult('accepted', 1, 'Dars xorijiy tilda olib borilgan.'),
                FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
                $category,
            );

            $this->assertSame(2.0, $result->point);
        }

        $foreignResult = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('accepted', 1, 'Dars xorijiy tilda olib borilgan.'),
            FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
            'foreign_lang',
        );

        $this->assertSame('checking', $foreignResult->status);
        $this->assertSame(0.0, $foreignResult->point);

        $specification = KpiCriterionSpecification::criteria()[FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE];
        $this->assertSame(KpiCriterionSpecification::Maximum, $specification['formula']);
        $this->assertSame([
            'hold_degrees' => 2,
            'no_degrees' => 2,
            'foreign_lang' => null,
            'physical' => 2,
        ], $specification['scores']);
        $this->assertSame(2.0, $specification['ai_submission_max_point']);
        $this->assertSame(FixedPerResourceHumanReviewCriterionRule::twoOneTwoPrompt(), $specification['ai_prompt']);
    }

    public function test_backfill_changes_existing_accepted_one_point_resource_to_two_idempotently(): void
    {
        [$report, $criterion] = $this->context();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $datum = Datum::query()->create([
            'name' => 'Eski AI tasdiqlagan resurs',
            'material' => ['type' => 'file', 'path' => 'foreign-course.pdf'],
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => 1,
        ]);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
            '--dry-run' => true,
        ])
            ->expectsOutput('Qayta hisoblanadigan accepted resurslar: 1')
            ->assertSuccessful();
        $this->assertSame(1.0, $datum->fresh()->point);

        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 1')
            ->assertSuccessful();
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
        ])
            ->expectsOutput('Qayta hisoblangan accepted resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(2.0, $datum->fresh()->point);
        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'fixed_resource_point_recalculated')
            ->count());
        $this->assertSame(2.0, (float) Point::query()
            ->whereBelongsTo($report)
            ->whereBelongsTo($criterion)
            ->whereBelongsTo($owner)
            ->value('point'));
    }

    public function test_foreign_language_category_cannot_open_or_see_the_upload_action(): void
    {
        [, $criterion] = $this->context();
        $foreignLanguageTeacher = User::factory()->create(['degree' => 'foreign_lang']);

        $this->actingAs($foreignLanguageTeacher)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('upload.show', $criterion), false);

        $this->actingAs($foreignLanguageTeacher)
            ->get(route('upload.show', $criterion))
            ->assertForbidden();
    }

    public function test_backfill_rejects_unknown_criterion_scope(): void
    {
        $this->artisan('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => '9.9.9',
        ])
            ->expectsOutputToContain("Qat'iy resurs balli qoidasi topilmadi")
            ->assertFailed();
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            Evaluation::query()->firstOrCreate(
                ['code' => $category],
                ['name' => ['uz' => $category], 'status' => '1'],
            );
        }

        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $parent = Criterion::query()->create([
            'code' => '2',
            'name' => ['uz' => 'Pedagogik faoliyat'],
            'report_id' => $report->id,
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => FixedPerResourceHumanReviewCriterionRule::TWO_ONE_TWO_CODE,
            'name' => ['uz' => 'Xorijiy tilda dars olib borish'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'ai',
            'upload' => '1',
            'file_limit' => 1,
            'status' => '1',
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::twoOneTwoPrompt(),
            'ai_submission_max_point' => 2,
        ]);

        foreach ([
            'hold_degrees' => ['has' => '1', 'score' => 2],
            'no_degrees' => ['has' => '1', 'score' => 2],
            'foreign_lang' => ['has' => '0', 'score' => 0],
            'physical' => ['has' => '1', 'score' => 2],
        ] as $category => $configuration) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->id,
                'evaluation' => $category,
                ...$configuration,
            ]);
        }

        return [$report, $criterion];
    }
}
