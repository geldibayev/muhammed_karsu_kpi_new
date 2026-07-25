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

class AiQueueCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_candidates_without_changing_or_dispatching_them(): void
    {
        $datum = $this->createDatum(['status' => 'received']);
        Queue::fake();

        $this->artisan('kpi:ai:queue-pending', ['--dry-run' => true])
            ->expectsOutput('AI navbatiga qo‘yilishi kerak bo‘lgan resurslar: 1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('received', $datum->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
        ]);
    }

    public function test_command_queues_legacy_and_failed_ai_resources_once(): void
    {
        $legacy = $this->createDatum(['status' => 'received']);
        $failed = $this->createDatum();
        $failed->histories()->create([
            'user_id' => $failed->user_id,
            'type' => 'warning',
            'message' => 'Oldingi AI xatosi.',
            'message_type' => 'ai_failed',
        ]);
        $alreadyQueued = $this->createDatum();
        $this->markAsQueued($alreadyQueued);
        $evaluated = $this->createDatum();
        $evaluated->histories()->create([
            'user_id' => $evaluated->user_id,
            'type' => 'success',
            'message' => 'AI tekshirdi.',
            'message_type' => 'ai_evaluation',
        ]);
        $this->createDatum(['status' => 'accepted']);
        $this->createDatum(['status' => 'cancelled']);
        $this->createDatum(['status' => 'deleted']);
        $this->createDatum([], 'manual');
        Queue::fake();

        $this->artisan('kpi:ai:queue-pending')
            ->expectsOutput('AI navbatiga qo‘yildi: 2')
            ->assertSuccessful();
        $this->artisan('kpi:ai:queue-pending')
            ->expectsOutput('AI navbatiga qo‘yildi: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $legacy->id
                && $job->criterionId === $legacy->criterion_id,
        );
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $failed->id
                && $job->criterionId === $failed->criterion_id,
        );
        $this->assertSame('checking', $legacy->fresh()->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $legacy->id,
            'message_type' => 'ai_queued',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $failed->id,
            'message_type' => 'ai_queued',
        ]);
        $this->assertDatabaseCount('datum_histories', 5);
    }

    public function test_diagnostic_command_does_not_print_the_gemini_key(): void
    {
        config()->set('gemini.api_key', 'super-secret-production-key');
        $this->createDatum();

        $this->artisan('kpi:ai:diagnose')
            ->expectsOutputToContain('Gemini API kaliti')
            ->expectsOutputToContain('Mavjud')
            ->expectsOutputToContain('Xulosa:')
            ->doesntExpectOutputToContain('super-secret-production-key')
            ->assertSuccessful();
    }

    private function markAsQueued(Datum $datum): void
    {
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'info',
            'message' => 'Resurs yuborildi.',
            'message_type' => 'submission_created',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createDatum(array $attributes = [], string $checking = 'ai'): Datum
    {
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
            'ai_prompt' => 'Tekshiring.',
            'ai_model' => 'gemini-test',
        ]);

        return Datum::query()->create(array_merge([
            'name' => 'https://example.com/proof',
            'material' => ['type' => 'url', 'link' => 'https://example.com/proof'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ], $attributes));
    }
}
