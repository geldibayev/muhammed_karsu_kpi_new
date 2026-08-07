<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Support\ScopusCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckScopusAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_recalculates_resolved_tiers_and_requeues_only_unresolved_resources(): void
    {
        Queue::fake();
        [$report, $criterion, $user] = $this->createFixture();
        $structured = $this->createDatum($criterion, $user, 'Eski qoidadagi ball.', 5, 'q2');
        $reasonResolved = $this->createDatum($criterion, $user, 'Scopus jurnalining Q3 kvartili aniq tasdiqlangan.', 4);
        $conference = $this->createDatum($criterion, $user, 'Web of Science conference proceedings materiali aniq tasdiqlangan.', 2.5);
        $unresolved = $this->createDatum($criterion, $user, 'Scopus maqolasi, lekin kvartil ko‘rsatilmagan.', 5);
        $conflicting = $this->createDatum($criterion, $user, 'Manbalarda Scopus jurnal kvartili Q1 va Q2 deb zid ko‘rsatilgan.', 5);

        $this->artisan('kpi:criteria:recheck-3-1-3-publications', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(15.0, $structured->fresh()->point);
        $this->assertSame('q2', $structured->fresh()->publication_tier);
        $this->assertSame(10.0, $reasonResolved->fresh()->point);
        $this->assertSame('q3', $reasonResolved->fresh()->publication_tier);
        $this->assertSame(5.0, $conference->fresh()->point);
        $this->assertSame('conference', $conference->fresh()->publication_tier);
        $this->assertSame('checking', $unresolved->fresh()->status);
        $this->assertSame(0.0, $unresolved->fresh()->point);
        $this->assertNull($unresolved->fresh()->publication_tier);
        $this->assertSame('checking', $conflicting->fresh()->status);
        $this->assertSame(0.0, $conflicting->fresh()->point);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $unresolved->getKey()
                && $job->criterionId === $criterion->getKey(),
        );
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);

        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $structured->getKey(),
            'message_type' => 'scopus_tier_score_recalculated',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $unresolved->getKey(),
            'message_type' => 'ai_scopus_recheck_queued',
        ]);

        $this->artisan('kpi:criteria:recheck-3-1-3-publications', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
    }

    public function test_command_is_dry_run_by_default(): void
    {
        Queue::fake();
        [$report, $criterion, $user] = $this->createFixture();
        $datum = $this->createDatum($criterion, $user, 'Web of Science Q4 jurnal maqolasi.', 4);

        $this->artisan('kpi:criteria:recheck-3-1-3-publications', [
            'report' => $report->getKey(),
        ])->assertSuccessful();

        $this->assertSame(4.0, $datum->fresh()->point);
        $this->assertNull($datum->fresh()->publication_tier);
        Queue::assertNothingPushed();
    }

    /** @return array{Report, Criterion, User} */
    private function createFixture(): array
    {
        $evaluation = Evaluation::query()->create([
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
            'code' => 'scopus-recheck',
            'name' => ['uz' => 'Scopus testi'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => ScopusCriterionRule::CODE,
            'name' => ['uz' => ScopusCriterionRule::NAME_UZ],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'ai_prompt' => ScopusCriterionRule::PROMPT,
            'ai_submission_max_point' => ScopusCriterionRule::MAXIMUM_POINT,
            'upload' => '1',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => $evaluation->code,
            'has' => '1',
            'score' => ScopusCriterionRule::MAXIMUM_POINT,
        ]);
        $user = User::factory()->create(['degree' => $evaluation->code]);

        return [$report, $criterion, $user];
    }

    private function createDatum(
        Criterion $criterion,
        User $user,
        string $reason,
        float $point,
        ?string $publicationTier = null,
    ): Datum {
        return Datum::query()->create([
            'name' => 'Scopus maqolasi',
            'material' => ['type' => 'file', 'path' => 'scopus.pdf'],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
            'publication_tier' => $publicationTier,
            'reason' => $reason,
        ]);
    }
}
