<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecheckAiHumanReviewsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_candidates_without_changing_them(): void
    {
        $datum = $this->createHumanReviewDatum();
        Queue::fake();

        $this->artisan('kpi:ai:recheck-human-reviews', ['--dry-run' => true])
            ->expectsOutput('Qayta AI tekshiruviga mos inson tekshiruvidagi resurslar: 1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $datum->refresh();
        $this->assertSame(3172011004, $datum->reviewer_hemis_id);
        $this->assertSame('Administrator tekshiruvi zarur.', $datum->reason);
    }

    public function test_command_requeues_only_current_ai_human_reviews_once(): void
    {
        $eligible = $this->createHumanReviewDatum();
        $alreadyRequeued = $this->createHumanReviewDatum();
        $alreadyRequeued->histories()->create([
            'user_id' => $alreadyRequeued->user_id,
            'type' => 'info',
            'message' => 'Oldin qayta navbatga qo‘yilgan.',
            'message_type' => 'ai_queued',
        ]);
        $transferred = $this->createHumanReviewDatum();
        $transferred->histories()->create([
            'user_id' => $transferred->user_id,
            'type' => 'info',
            'message' => 'Kriteriya o‘zgartirildi.',
            'message_type' => 'criterion_transferred',
        ]);
        $this->createHumanReviewDatum(status: 'accepted');
        $this->createHumanReviewDatum(checking: 'manual');
        Queue::fake();

        $this->artisan('kpi:ai:recheck-human-reviews', ['--force' => true])
            ->expectsOutput('Qayta AI tekshiruviga qo‘yildi: 1')
            ->assertSuccessful();
        $eligible->histories()->create([
            'user_id' => $eligible->user_id,
            'type' => 'warning',
            'message' => 'Qayta tekshiruvdan keyin ham dalil noaniq.',
            'message_type' => 'ai_evaluation',
        ]);
        $this->artisan('kpi:ai:recheck-human-reviews', ['--force' => true])
            ->expectsOutput('Qayta AI tekshiruviga mos resurs topilmadi.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $eligible->id
                && $job->criterionId === $eligible->criterion_id,
        );

        $eligible->refresh();
        $this->assertSame('checking', $eligible->status);
        $this->assertSame(0.0, $eligible->point);
        $this->assertNull($eligible->reviewer_hemis_id);
        $this->assertSame(
            'AI xulosasi yangi qaror qoidasi bo‘yicha qayta tekshirilmoqda.',
            $eligible->reason,
        );
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->id,
            'message_type' => 'ai_queued',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->id,
            'message_type' => 'ai_decision_rule_recheck_queued',
        ]);
    }

    public function test_command_requires_confirmation_without_force(): void
    {
        $datum = $this->createHumanReviewDatum();
        Queue::fake();

        $this->artisan('kpi:ai:recheck-human-reviews')
            ->expectsConfirmation('1 ta resursni qayta AI tekshiruviga yuborasizmi?', 'no')
            ->expectsOutput('Qayta navbatga qo‘yish bekor qilindi.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(3172011004, $datum->fresh()->reviewer_hemis_id);
    }

    private function createHumanReviewDatum(
        string $status = 'checking',
        string $checking = 'ai',
    ): Datum {
        $user = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Test mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'checking' => $checking,
            'ai_prompt' => 'Hujjatni tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        $datum = Datum::query()->create([
            'name' => 'proof.pdf',
            'material' => ['type' => 'file', 'disk' => 'local', 'path' => 'proof.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'reviewer_hemis_id' => 3172011004,
            'status' => $status,
            'point' => 0,
            'reason' => 'Administrator tekshiruvi zarur.',
        ]);
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'warning',
            'message' => 'Aniq rad sababi. Administrator tekshiruvi zarur.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }
}
