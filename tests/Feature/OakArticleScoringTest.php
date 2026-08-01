<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use App\Support\OakArticleCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OakArticleScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_recalculates_existing_resources_without_ai_and_is_idempotent(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Asosiy hisobot');
        $criterion = $this->createOakCriterion($report, $formula);
        $otherReport = $this->createReport('Boshqa hisobot', '0');
        $otherCriterion = $this->createOakCriterion($otherReport, $formula);
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);

        $fromAiHistory = $this->createAcceptedDatum($withDegree, $criterion, 1, [
            'article' => ['authors_num' => 2],
        ], 'Maqolada jami 3 nafar muallif mavjud.');
        $fromAiHistory->histories()->create([
            'user_id' => $withDegree->id,
            'type' => 'success',
            'message' => 'OAK jurnali tasdiqlandi. Mualliflar soni: 4.',
            'message_type' => 'ai_evaluation',
        ]);
        $fromReason = $this->createAcceptedDatum(
            $withoutDegree,
            $criterion,
            1,
            [],
            'Maqolada jami 3 nafar muallif mavjud.',
        );
        $fromMaterial = $this->createAcceptedDatum($withoutDegree, $criterion, 1, [
            'article' => ['authors_num' => 2],
        ]);
        $isolatedDatum = $this->createAcceptedDatum($withoutDegree, $otherCriterion, 9, [
            'article' => ['authors_num' => 1],
        ]);

        $this->artisan('kpi:recalculate-oak-article-points', ['report' => $report->id])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertNull($fromAiHistory->fresh()->author_count);
        $this->assertSame(1.0, $fromAiHistory->fresh()->point);
        $this->assertDatabaseCount('points', 0);
        $this->assertDatabaseMissing('datum_histories', [
            'message_type' => 'oak_article_rule_recalculated',
        ]);

        $this->artisan('kpi:recalculate-oak-article-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('APPLIED')->assertSuccessful();

        $this->assertDatumScore($fromAiHistory, 4, 0.125);
        $this->assertDatumScore($fromReason, 3, 0.25);
        $this->assertDatumScore($fromMaterial, 2, 0.375);
        $this->assertDatumScore($isolatedDatum, null, 9);
        $this->assertSame(3, $this->recalculationHistoryCount());
        $this->assertPointEquals($withDegree, $criterion, 0.125);
        $this->assertPointEquals($withoutDegree, $criterion, 0.625);

        $this->artisan('kpi:recalculate-oak-article-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('APPLIED')->assertSuccessful();

        $this->assertSame(3, $this->recalculationHistoryCount());
        $this->assertPointEquals($withoutDegree, $criterion, 0.625);
    }

    public function test_human_review_requires_author_count_and_calculates_point_on_server(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Inson tekshiruvi hisoboti');
        $criterion = $this->createOakCriterion($report, $formula);
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $datum = Datum::query()->create([
            'name' => 'Tekshiriladigan OAK maqolasi',
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'reviewer_hemis_id' => $reviewer->hemis_id,
            'status' => 'checking',
            'point' => 0,
        ]);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertSessionHasErrors('author_count');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), ['author_count' => 3])
            ->assertRedirect(route('ai-human-reviews.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'author_count' => 3,
            'point' => 0.25,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'message_type' => 'manual_review_approved',
        ]);
        $this->assertPointEquals($owner, $criterion, 0.25);
    }

    public function test_apply_stops_without_partial_changes_when_author_count_is_unknown(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Tekshiruv hisoboti');
        $criterion = $this->createOakCriterion($report, $formula);
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $known = $this->createAcceptedDatum($withDegree, $criterion, 1, [
            'article' => ['authors_num' => 2],
        ]);
        $unknown = $this->createAcceptedDatum($withDegree, $criterion, 1);

        $this->artisan('kpi:recalculate-oak-article-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain((string) $unknown->id)->assertFailed();

        $this->assertDatumScore($known, null, 1);
        $this->assertDatumScore($unknown, null, 1);
        $this->assertSame(0, $this->recalculationHistoryCount());
    }

    public function test_final_score_does_not_exceed_the_evaluation_category_maximum(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Chegara hisoboti');
        $criterion = $this->createOakCriterion($report, $formula);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);

        foreach (range(1, 5) as $index) {
            Datum::query()->create([
                'name' => 'OAK maqolasi '.$index,
                'user_id' => $withoutDegree->id,
                'criterion_id' => $criterion->id,
                'status' => 'accepted',
                'point' => 0.75,
                'author_count' => 1,
            ]);
        }

        $this->artisan('kpi:recalculate-oak-article-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('criterion_points', [
            'user_id' => $withoutDegree->id,
            'criterion_id' => $criterion->id,
            'point' => 3.75,
            'files' => 5,
        ]);
        $this->assertPointEquals($withoutDegree, $criterion, 3);
    }

    private function createEvaluationCategories(): void
    {
        foreach (['hold_degrees', 'no_degrees'] as $code) {
            Evaluation::query()->create([
                'code' => $code,
                'name' => ['uz' => $code],
                'status' => '1',
            ]);
        }
    }

    private function createMaximumFormula(): Formula
    {
        return Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ballga asoslangan'],
            'status' => '1',
        ]);
    }

    private function createReport(string $name, string $status = '1'): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => $status,
        ]);
    }

    private function createOakCriterion(Report $report, Formula $formula): Criterion
    {
        $parent = Criterion::query()->create([
            'code' => '3',
            'name' => ['uz' => 'Ilmiy-innovatsion faoliyat'],
            'report_id' => $report->id,
            'formula_id' => $formula->id,
        ]);
        $criterion = Criterion::query()->create([
            'code' => OakArticleCriterionRule::CODE,
            'name' => ['uz' => 'OAK maqolasi'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);

        foreach (['hold_degrees' => 2, 'no_degrees' => 3] as $category => $maximum) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->id,
                'evaluation' => $category,
                'has' => '1',
                'score' => $maximum,
            ]);
        }

        return $criterion;
    }

    /** @param array<string, mixed> $material */
    private function createAcceptedDatum(
        User $user,
        Criterion $criterion,
        float $point,
        array $material = [],
        ?string $reason = null,
    ): Datum {
        return Datum::query()->create([
            'name' => 'OAK maqolasi',
            'material' => $material,
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => $point,
            'reason' => $reason,
        ]);
    }

    private function assertDatumScore(Datum $datum, ?int $authorCount, float $point): void
    {
        $datum->refresh();

        $this->assertSame($authorCount, $datum->author_count);
        $this->assertEqualsWithDelta($point, $datum->point, 0.0001);
    }

    private function assertPointEquals(User $user, Criterion $criterion, float $point): void
    {
        $actual = Point::query()
            ->where('user_id', $user->id)
            ->where('criterion_id', $criterion->id)
            ->value('point');

        $this->assertNotNull($actual);
        $this->assertEqualsWithDelta($point, (float) $actual, 0.0001);
    }

    private function recalculationHistoryCount(): int
    {
        return DatumHistory::query()
            ->where('message_type', 'oak_article_rule_recalculated')
            ->count();
    }
}
