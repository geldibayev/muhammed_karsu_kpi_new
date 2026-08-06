<?php

namespace Tests\Feature;

use App\Actions\RequeueTranslatedEducationalLiteratureEvaluations;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckTranslatedEducationalLiteratureAiEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_candidates_without_changing_or_dispatching_them(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $datum = $this->acceptedDatum($criterion);

        $this->artisan('kpi:recheck-translated-literature-ai-evaluations', [
            'report' => $report->getKey(),
        ])
            ->expectsOutputToContain('qayta tekshiruvga mos tasdiqlangan resurslar: 1')
            ->expectsOutputToContain('Dry-run: 1 ta resurs')
            ->assertSuccessful();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(3.0, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => RequeueTranslatedEducationalLiteratureEvaluations::HISTORY_TYPE,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_apply_requeues_all_accepted_one_four_resources_in_the_report_idempotently(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $eligible = $this->acceptedDatum($criterion);
        $manuallyApproved = $this->acceptedDatum($criterion);
        $manuallyApproved->histories()->create([
            'user_id' => $manuallyApproved->user_id,
            'type' => 'success',
            'message' => 'Mas’ul tomonidan eski qoida bilan tasdiqlandi.',
            'message_type' => 'manual_review_approved',
        ]);

        CriterionPoint::query()->create([
            'user_id' => $eligible->user_id,
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 3,
            'files' => 1,
        ]);
        Point::query()->create([
            'user_id' => $eligible->user_id,
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 3,
        ]);

        $otherCriterion = Criterion::query()->create([
            'code' => '1.3',
            'name' => ['uz' => 'Boshqa mezon'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'status' => '1',
        ]);
        $otherCriterionDatum = $this->acceptedDatum($otherCriterion);
        [, $otherReportCriterion] = $this->context();
        $otherReportDatum = $this->acceptedDatum($otherReportCriterion);

        $this->artisan('kpi:recheck-translated-literature-ai-evaluations', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('checking holatiga o‘tkazildi: 2')
            ->expectsOutputToContain('AI navbatiga muvaffaqiyatli qo‘yildi: 2')
            ->assertSuccessful();

        foreach ([$eligible, $manuallyApproved] as $requeued) {
            $requeued->refresh();
            $this->assertSame('checking', $requeued->status);
            $this->assertSame(0.0, $requeued->point);
            $this->assertNull($requeued->author_count);
            $this->assertNull($requeued->page_count);
            $this->assertNull($requeued->reviewer_hemis_id);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $requeued->getKey(),
                'message_type' => RequeueTranslatedEducationalLiteratureEvaluations::HISTORY_TYPE,
            ]);
        }

        $this->assertSame('accepted', $otherCriterionDatum->fresh()->status);
        $this->assertSame('accepted', $otherReportDatum->fresh()->status);
        $this->assertDatabaseMissing('criterion_points', [
            'report_id' => $report->getKey(),
            'user_id' => $eligible->user_id,
            'criterion_id' => $criterion->getKey(),
        ]);
        $this->assertDatabaseMissing('points', [
            'report_id' => $report->getKey(),
            'user_id' => $eligible->user_id,
            'criterion_id' => $criterion->getKey(),
        ]);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);

        $this->artisan('kpi:recheck-translated-literature-ai-evaluations', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
    }

    public function test_command_rejects_an_unknown_report_and_invalid_limit(): void
    {
        $this->artisan('kpi:recheck-translated-literature-ai-evaluations', [
            'report' => 999999,
        ])
            ->expectsOutputToContain('Hisobot topilmadi')
            ->assertFailed();

        [$report] = $this->context();

        $this->artisan('kpi:recheck-translated-literature-ai-evaluations', [
            'report' => $report->getKey(),
            '--limit' => 0,
        ])
            ->expectsOutputToContain('--limit musbat butun son bo‘lishi kerak')
            ->assertFailed();
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '1.4',
            'name' => ['uz' => 'Tarjima adabiyoti'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'ai_prompt' => 'Tarjimani tekshiring.',
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);

        return [$report, $criterion];
    }

    private function acceptedDatum(Criterion $criterion): Datum
    {
        $user = User::factory()->create();

        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 3,
            'author_count' => 2,
            'page_count' => 160,
            'reason' => 'Eski qoida bo‘yicha tasdiqlangan.',
            'reviewer_hemis_id' => '1234567890',
        ]);
    }
}
