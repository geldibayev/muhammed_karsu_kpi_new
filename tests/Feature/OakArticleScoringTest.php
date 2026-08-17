<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
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

    public function test_report_recalculation_updates_known_three_one_two_author_shares_only(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('3.1.2 hisoboti');
        $criterion = $this->createOakCriterion(
            $report,
            $formula,
            Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE,
        );
        $otherReport = $this->createReport('Boshqa hisobot');
        $otherCriterion = $this->createOakCriterion(
            $otherReport,
            $formula,
            Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE,
        );
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegree = User::factory()->create(['degree' => 'no_degrees']);

        $degreeDatum = $this->createAcceptedDatum($withDegree, $criterion, 2);
        $degreeDatum->update(['author_count' => 2]);
        $withoutDegreeDatum = $this->createAcceptedDatum($withoutDegree, $criterion, 2);
        $withoutDegreeDatum->update(['author_count' => 3]);
        $unknownDatum = $this->createAcceptedDatum($withoutDegree, $criterion, 2);
        $checkingDatum = $this->createAcceptedDatum($withoutDegree, $criterion, 2);
        $checkingDatum->update(['status' => 'checking', 'author_count' => 2]);
        $otherReportDatum = $this->createAcceptedDatum($withoutDegree, $otherCriterion, 2);
        $otherReportDatum->update(['author_count' => 2]);

        app(RecalculateReportPoints::class)->handle($report);
        app(RecalculateReportPoints::class)->handle($report);

        $this->assertDatumScore($degreeDatum, 2, 0.25);
        $this->assertDatumScore($withoutDegreeDatum, 3, 0.25);
        $this->assertDatumScore($unknownDatum, null, 2);
        $this->assertDatumScore($checkingDatum, 2, 2);
        $this->assertDatumScore($otherReportDatum, 2, 2);
        $this->assertSame(2, DatumHistory::query()
            ->where('message_type', 'criterion_3_1_2_point_recalculated')
            ->count());
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

    public function test_cancelled_reapproval_only_accepts_author_count_and_calculates_point_on_server(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Qayta tasdiqlash hisoboti');
        $criterion = $this->createOakCriterion($report, $formula);
        $reviewer = User::factory()->create();
        config()->set('kpi.super_admin_hemis_ids', []);
        config()->set('kpi.accepted_ai_reviewer_hemis_id', 9999999998);
        config()->set('kpi.assigned_final_decision_reviewer_hemis_id', 9999999997);

        foreach (['hold_degrees' => 0.25, 'no_degrees' => 0.375] as $degree => $expectedPoint) {
            $owner = User::factory()->create(['degree' => $degree]);
            $datum = Datum::query()->create([
                'name' => 'Qayta tasdiqlanadigan OAK maqolasi',
                'user_id' => $owner->id,
                'criterion_id' => $criterion->id,
                'reviewer_hemis_id' => $reviewer->hemis_id,
                'status' => 'cancelled',
                'point' => 0,
            ]);

            $this->actingAs($reviewer)
                ->get(route('upload.details', $datum))
                ->assertOk()
                ->assertSee('name="author_count"', false)
                ->assertDontSee('name="point"', false);

            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum))
                ->assertSessionHasErrors('author_count');
            foreach ([0, 1001] as $invalidAuthorCount) {
                $this->actingAs($reviewer)
                    ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                        'author_count' => $invalidAuthorCount,
                    ])
                    ->assertSessionHasErrors('author_count');
            }
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), [
                    'author_count' => 2,
                    'point' => 99,
                ])
                ->assertSessionHasErrors('point');
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['author_count' => 2])
                ->assertRedirect(route('upload.details', $datum))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('data', [
                'id' => $datum->id,
                'status' => 'accepted',
                'author_count' => 2,
                'point' => $expectedPoint,
            ]);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->id,
                'user_id' => $reviewer->id,
                'message_type' => 'human_override_approved',
            ]);
            $this->assertPointEquals($owner, $criterion, $expectedPoint);
        }
    }

    public function test_three_one_two_finalized_overrides_use_author_count_and_server_score(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('3.1.2 yakuniy qarorlar');
        $criterion = $this->createOakCriterion(
            $report,
            $formula,
            Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE,
        );
        $superAdmin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $accepted = $this->createAcceptedDatum($owner, $criterion, 2);
        $accepted->update(['author_count' => 1]);
        $cancelled = $this->createAcceptedDatum($owner, $criterion, 0);
        $cancelled->update(['status' => 'cancelled']);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('name="author_count"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'point' => 1,
                'score_change_reason' => 'Mualliflar soni tekshirildi.',
            ])
            ->assertSessionHasErrors('point');
        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'author_count' => 3,
                'score_change_reason' => 'Mualliflar soni tekshirildi.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), [
                'author_count' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatumScore($accepted, 3, 0.25);
        $this->assertDatumScore($cancelled, 2, 0.375);
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

    public function test_file_limit_command_keeps_the_four_highest_accepted_resources_and_is_idempotent(): void
    {
        $this->createEvaluationCategories();
        $formula = $this->createMaximumFormula();
        $report = $this->createReport('Fayl cheklovi hisoboti');
        $criterion = $this->createOakCriterion($report, $formula);
        $otherReport = $this->createReport('Boshqa hisobot', '0');
        $otherCriterion = $this->createOakCriterion($otherReport, $formula);
        $limitedUser = User::factory()->create(['degree' => 'no_degrees']);
        $withinLimitUser = User::factory()->create(['degree' => 'hold_degrees']);

        $limitedData = collect([0.75, 0.60, 0.50, 0.40, 0.40, 0.10])
            ->map(fn (float $point): Datum => $this->createAcceptedDatum(
                $limitedUser,
                $criterion,
                $point,
                ['article' => ['authors_num' => 1]],
            ));

        foreach ([0.50, 0.40, 0.30] as $point) {
            $this->createAcceptedDatum(
                $withinLimitUser,
                $criterion,
                $point,
                ['article' => ['authors_num' => 1]],
            );
        }

        foreach (range(1, 5) as $index) {
            $this->createAcceptedDatum($limitedUser, $otherCriterion, $index);
        }

        $this->artisan('kpi:criteria:enforce-3-1-1-file-limit', ['report' => $report->id])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame(6, Datum::query()->whereKey($limitedData->pluck('id'))->where('status', 'accepted')->count());

        $this->artisan('kpi:criteria:enforce-3-1-1-file-limit', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('APPLIED')->assertSuccessful();

        $keptIds = $limitedData->sortByDesc('point')->take(4)->pluck('id');
        $cancelledIds = $limitedData->pluck('id')->diff($keptIds);

        $this->assertSame(4, Datum::query()->whereKey($keptIds)->where('status', 'accepted')->count());
        $this->assertSame(2, Datum::query()->whereKey($cancelledIds)->where('status', 'cancelled')->where('point', 0)->count());
        $this->assertSame('accepted', $limitedData[3]->fresh()->status);
        $this->assertSame('cancelled', $limitedData[4]->fresh()->status);
        $this->assertSame(3, Datum::query()->where('user_id', $withinLimitUser->id)->where('status', 'accepted')->count());
        $this->assertSame(5, Datum::query()->where('criterion_id', $otherCriterion->id)->where('status', 'accepted')->count());
        $this->assertSame(2, DatumHistory::query()->where('message_type', 'oak_article_file_limit_enforced')->count());
        $this->assertDatabaseHas('criterion_points', [
            'user_id' => $limitedUser->id,
            'criterion_id' => $criterion->id,
            'files' => 4,
        ]);

        $this->artisan('kpi:criteria:enforce-3-1-1-file-limit', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, DatumHistory::query()->where('message_type', 'oak_article_file_limit_enforced')->count());
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

    private function createOakCriterion(
        Report $report,
        Formula $formula,
        string $code = OakArticleCriterionRule::CODE,
    ): Criterion {
        $parent = Criterion::query()->create([
            'code' => '3',
            'name' => ['uz' => 'Ilmiy-innovatsion faoliyat'],
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => 'OAK maqolasi'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'ai',
            'file_limit' => 4,
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
