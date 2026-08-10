<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Data\AiEvaluationResult;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use App\Services\AiResourceDatePolicy;
use App\Services\AiSubmissionEvaluator;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\KpiCriterionSpecification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class CriterionFourOneOneAiScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(ProcessAiDatumEvaluation::RATE_LIMIT_KEY);
    }

    public function test_ai_only_decides_status_and_server_assigns_point_seventy_five(): void
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            $accepted = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
                new AiEvaluationResult('accepted', 99, 'OAV chiqishi tasdiqlandi.'),
                FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
                $category,
            );

            $this->assertSame('accepted', $accepted->status);
            $this->assertSame(0.75, $accepted->point);
        }

        $checking = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('checking', 99, 'Hujjat xira.'),
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
            'foreign_lang',
        );
        $cancelled = FixedPerResourceHumanReviewCriterionRule::normalizeAiResult(
            new AiEvaluationResult('cancelled', 99, 'Material mezonga aloqasiz.'),
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
            'no_degrees',
        );

        $this->assertSame(0.0, $checking->point);
        $this->assertSame(0.0, $cancelled->point);

        $specification = KpiCriterionSpecification::criteria()[
            FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE
        ];

        $this->assertSame(KpiCriterionSpecification::Maximum, $specification['formula']);
        $this->assertSame(4, $specification['file_limit']);
        $this->assertSame(0.75, $specification['ai_submission_max_point']);
        $this->assertFalse($specification['divide_ai_point_by_authors']);
        $this->assertSame(
            FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            $specification['ai_prompt'],
        );
        $this->assertStringContainsString('Gazetada chop etilgan maqola', $specification['ai_prompt']);
        $this->assertStringContainsString('televideniye chiqishi', $specification['ai_prompt']);
        $this->assertStringContainsString('resource_date', $specification['ai_prompt']);
    }

    public function test_uncertain_ai_result_goes_to_existing_reviewer_and_human_approval_uses_server_point(): void
    {
        [, $criterion] = $this->context();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        config()->set('kpi.ai_human_review_criterion_reviewers', [
            ...config('kpi.ai_human_review_criterion_reviewers'),
            '4.1.1' => $reviewer->hemis_id,
        ]);
        $datum = Datum::query()->create([
            'name' => 'Televideniye chiqishi bo‘yicha ma’lumotnoma',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'point' => 0,
        ]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(AiEvaluationResult::checking('Efir sanasi aniq o‘qilmadi.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->getKey(), $criterion->getKey()))
            ->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame($reviewer->hemis_id, $datum->reviewer_hemis_id);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'ai_human_review_assigned',
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index', ['criterion' => $criterion->getKey()]))
            ->assertOk()
            ->assertSee(route('reviews.show', [
                'datum' => $datum,
                'criterion' => $criterion->getKey(),
                'page' => 1,
            ]));

        $returnFilters = [
            'criterion' => $criterion->getKey(),
            'page' => 3,
        ];
        $detailsUrl = route('reviews.show', ['datum' => $datum, ...$returnFilters]);
        $approvalUrl = route('reviews.approve', ['datum' => $datum, ...$returnFilters]);

        $this->actingAs($reviewer)
            ->get($detailsUrl)
            ->assertOk()
            ->assertSee($approvalUrl)
            ->assertSee(route('ai-human-reviews.index', $returnFilters));

        $this->actingAs($reviewer)
            ->from($detailsUrl)
            ->patch($approvalUrl, ['point' => 3])
            ->assertSessionHasErrors('point');
        $this->actingAs($reviewer)
            ->patch($approvalUrl, ['return_url' => 'https://example.com/unsafe'])
            ->assertRedirect(route('ai-human-reviews.index', $returnFilters));

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(0.75, $datum->fresh()->point);
    }

    public function test_server_enforces_report_period_for_oav_resource(): void
    {
        config()->set('kpi.report_period_start', '2025-09-01');
        config()->set('kpi.report_period_end', '2026-08-31');
        [, $criterion] = $this->context();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $datum = Datum::query()->create([
            'name' => 'Gazeta maqolasi',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
        ]);
        $policy = new AiResourceDatePolicy;

        $accepted = $policy->enforce(
            $datum,
            new AiEvaluationResult(
                'accepted',
                0.75,
                'Maqola tasdiqlandi.',
                resourceDate: '2025-09-01',
            ),
        );
        $outsidePeriod = $policy->enforce(
            $datum,
            new AiEvaluationResult(
                'accepted',
                0.75,
                'Maqola tasdiqlandi.',
                resourceDate: '2025-08-31',
            ),
        );
        $missingDate = $policy->enforce(
            $datum,
            new AiEvaluationResult('accepted', 0.75, 'Maqola tasdiqlandi.'),
        );

        $this->assertSame('accepted', $accepted->status);
        $this->assertSame(0.75, $accepted->point);
        $this->assertSame('cancelled', $outsidePeriod->status);
        $this->assertSame(0.0, $outsidePeriod->point);
        $this->assertSame('checking', $missingDate->status);
        $this->assertSame(0.0, $missingDate->point);
    }

    public function test_four_accepted_resources_are_capped_at_category_maximum(): void
    {
        [$report, $criterion] = $this->context();
        $regularOwner = User::factory()->create(['degree' => 'no_degrees']);
        $foreignLanguageOwner = User::factory()->create(['degree' => 'foreign_lang']);

        foreach ([$regularOwner, $foreignLanguageOwner] as $owner) {
            foreach (range(1, 4) as $index) {
                Datum::query()->create([
                    'name' => "Tasdiqlangan OAV resursi {$index}",
                    'user_id' => $owner->getKey(),
                    'criterion_id' => $criterion->getKey(),
                    'status' => 'accepted',
                    'point' => 0.75,
                ]);
            }
        }

        app(RecalculateReportPoints::class)->handle($report);

        $this->assertSame(3.0, $this->finalPoint($report, $criterion, $regularOwner));
        $this->assertSame(2.0, $this->finalPoint($report, $criterion, $foreignLanguageOwner));
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $category) {
            Evaluation::query()->updateOrCreate(
                ['code' => $category],
                ['name' => ['uz' => $category], 'status' => '1'],
            );
        }

        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ballga asoslangan'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => '2025-2026'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'code' => '4',
            'name' => ['uz' => 'Boshqa faoliyat'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
            'name' => ['uz' => 'OAV yoki ijtimoiy tarmoqlardagi chiqish'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'file_limit' => 4,
            'upload' => '1',
            'status' => '1',
            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            'ai_submission_max_point' => 0.75,
            'divide_ai_point_by_authors' => false,
        ]);

        foreach ([
            'hold_degrees' => 3,
            'no_degrees' => 3,
            'foreign_lang' => 2,
            'physical' => 3,
        ] as $category => $score) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => $score,
            ]);
        }

        return [$report, $criterion];
    }

    private function finalPoint(
        Report $report,
        Criterion $criterion,
        User $owner,
    ): float {
        return (float) Point::query()
            ->whereBelongsTo($report)
            ->whereBelongsTo($criterion)
            ->whereBelongsTo($owner)
            ->value('point');
    }
}
