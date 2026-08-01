<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrintedEducationalLiteratureScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_recalculates_existing_resources_without_gemini_and_is_idempotent(): void
    {
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Unlimited,
            'name' => ['uz' => 'Cheklanmagan'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Asosiy hisobot'],
            'status' => '1',
        ]);
        $otherReport = Report::query()->create([
            'name' => ['uz' => 'Boshqa hisobot'],
            'status' => '0',
        ]);
        $textbook = $this->createCriterion($report, $formula, '1.2');
        $studyGuide = $this->createCriterion($report, $formula, '1.3');
        $otherCriterion = $this->createCriterion($otherReport, $formula, '1.2');
        $user = User::factory()->create();

        $fromMaterial = $this->createAcceptedDatum($user, $textbook, 9, [
            'article' => ['page_count' => 160, 'authors_num' => 2],
        ]);
        $fromReason = $this->createAcceptedDatum(
            $user,
            $studyGuide,
            9,
            reason: 'Jami sahifalar soni: 80. Mualliflar soni: 2.',
        );
        $unresolved = $this->createAcceptedDatum($user, $textbook, 7);
        $isolated = $this->createAcceptedDatum($user, $otherCriterion, 8, [
            'page_count' => 160,
            'author_count' => 1,
        ]);

        $this->artisan('kpi:recalculate-printed-literature-points', ['report' => $report->id])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain((string) $unresolved->id)
            ->assertSuccessful();

        $this->assertSame(9.0, $fromMaterial->fresh()->point);
        $this->assertNull($fromMaterial->fresh()->page_count);

        $this->artisan('kpi:recalculate-printed-literature-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->expectsOutputToContain('APPLIED')->assertSuccessful();

        $this->assertDatumScore($fromMaterial, 160, 2, 2.0);
        $this->assertDatumScore($fromReason, 80, 2, 0.75);
        $this->assertDatumScore($unresolved, null, null, 7.0);
        $this->assertDatumScore($isolated, null, null, 8.0);
        $this->assertSame(2, $this->recalculationHistoryCount());

        $this->artisan('kpi:recalculate-printed-literature-points', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, $this->recalculationHistoryCount());
    }

    private function createCriterion(Report $report, Formula $formula, string $code): Criterion
    {
        $criterion = Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code],
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 100,
        ]);

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
            'name' => 'Bosma o\'quv adabiyoti',
            'material' => $material,
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => $point,
            'reason' => $reason,
        ]);
    }

    private function assertDatumScore(
        Datum $datum,
        ?int $pageCount,
        ?int $authorCount,
        float $point,
    ): void {
        $datum->refresh();

        $this->assertSame($pageCount, $datum->page_count);
        $this->assertSame($authorCount, $datum->author_count);
        $this->assertEqualsWithDelta($point, $datum->point, 0.0001);
    }

    private function recalculationHistoryCount(): int
    {
        return DatumHistory::query()
            ->where('message_type', 'printed_literature_rule_recalculated')
            ->count();
    }
}
