<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use App\Support\PatentCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class RecheckPatentAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_counts_all_old_accepted_patents_without_changing_them(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $aiAccepted = $this->acceptedDatum($criterion, 1, 5);
        $manuallyAccepted = $this->acceptedDatum($criterion, 2, 3);
        $manuallyAccepted->histories()->create([
            'user_id' => $manuallyAccepted->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);

        $this->artisan('kpi:criteria:recheck-3-1-8-patents', ['report' => $report->getKey()])
            ->expectsOutputToContain('eski accepted patent resurslari: 2')
            ->expectsOutputToContain('Dry-run: 2 ta resurs')
            ->assertSuccessful();

        $this->assertSame('accepted', $aiAccepted->fresh()->status);
        $this->assertSame(5.0, $aiAccepted->fresh()->point);
        $this->assertSame('accepted', $manuallyAccepted->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'message_type' => 'ai_patent_recheck_queued',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_requeues_every_accepted_patent_and_is_report_scoped_and_idempotent(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $first = $this->acceptedDatum($criterion, 1, 1.5);
        $first->histories()->create([
            'user_id' => $first->user_id,
            'type' => 'success',
            'message' => 'Eski AI tasdiqladi.',
            'message_type' => 'ai_evaluation',
        ]);
        $second = $this->acceptedDatum($criterion, 2, 4);
        $second->histories()->create([
            'user_id' => $second->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);
        $cancelled = $this->datum($criterion, 'cancelled', 0);
        $otherCriterion = Criterion::query()->create([
            'code' => '3.1.9',
            'name' => ['uz' => 'Boshqa mezon'],
            'report_id' => $report->getKey(),
            'formula_id' => $criterion->formula_id,
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);
        $otherCriterionDatum = $this->datum($otherCriterion, 'accepted', 2);
        [$otherReport, $otherReportCriterion] = $this->context();
        $otherReportDatum = $this->acceptedDatum($otherReportCriterion, 1, 3);

        foreach ([$first, $second] as $datum) {
            CriterionPoint::query()->create([
                'user_id' => $datum->user_id,
                'criterion_id' => $criterion->getKey(),
                'report_id' => $report->getKey(),
                'point' => $datum->point,
                'files' => 1,
            ]);
            Point::query()->create([
                'user_id' => $datum->user_id,
                'criterion_id' => $criterion->getKey(),
                'report_id' => $report->getKey(),
                'point' => $datum->point,
            ]);
        }

        $this->artisan('kpi:criteria:recheck-3-1-8-patents', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain("AI qayta tekshiruviga qo'yildi: 2")
            ->assertSuccessful();

        foreach ([$first, $second] as $datum) {
            $datum->refresh();
            $this->assertSame('checking', $datum->status);
            $this->assertSame(0.0, $datum->point);
            $this->assertNull($datum->author_count);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'ai_patent_recheck_queued',
            ]);
            $this->assertDatabaseMissing('criterion_points', [
                'report_id' => $report->getKey(),
                'user_id' => $datum->user_id,
                'criterion_id' => $criterion->getKey(),
            ]);
            $this->assertDatabaseMissing('points', [
                'report_id' => $report->getKey(),
                'user_id' => $datum->user_id,
                'criterion_id' => $criterion->getKey(),
            ]);
        }

        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('accepted', $otherCriterionDatum->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);

        $this->artisan('kpi:criteria:recheck-3-1-8-patents', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('eski accepted patent resurslari: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
        $this->assertSame($otherReport->getKey(), $otherReportDatum->criterion->report_id);
    }

    public function test_dispatch_failure_restores_the_old_accepted_state_for_safe_retry(): void
    {
        [$report, $criterion] = $this->context();
        $datum = $this->acceptedDatum($criterion, 3, 3);
        Queue::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('Queue unavailable'));

        $this->artisan('kpi:criteria:recheck-3-1-8-patents', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertFailed();

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(3.0, $datum->point);
        $this->assertSame(3, $datum->author_count);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'ai_patent_recheck_dispatch_failed',
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'ai_patent_recheck_queued',
        ]);
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'hold_degrees'],
            ['name' => ['uz' => 'Ilmiy darajali'], 'status' => '1'],
        );
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Unlimited],
            ['name' => ['uz' => 'Cheklanmagan'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => PatentCriterionRule::CODE,
            'name' => ['uz' => 'Patent'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'ai_prompt' => PatentCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
            'ai_submission_max_point' => 4,
            'divide_ai_point_by_authors' => false,
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 3,
        ]);

        return [$report, $criterion];
    }

    private function acceptedDatum(Criterion $criterion, int $authorCount, float $point): Datum
    {
        return $this->datum($criterion, 'accepted', $point, $authorCount);
    }

    private function datum(
        Criterion $criterion,
        string $status,
        float $point,
        ?int $authorCount = null,
    ): Datum {
        $user = User::factory()->create(['degree' => 'hold_degrees']);

        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'author_count' => $authorCount,
            'reason' => 'Oldingi qaror.',
        ]);
    }
}
