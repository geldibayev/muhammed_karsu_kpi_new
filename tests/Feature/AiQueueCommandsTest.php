<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use Gemini\Exceptions\ErrorException;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AiQueueCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearAiWorkerCache();
    }

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
        $this->markAsQueued($legacy);
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
        $this->assertDatabaseCount('datum_histories', 6);
    }

    public function test_recovery_requeues_a_stale_orphan_once_even_after_an_older_evaluation(): void
    {
        config()->set('queue.default', 'database');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $datum = $this->createDatum();
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'success',
            'message' => 'Oldingi AI tekshiruvi.',
            'message_type' => 'ai_evaluation',
        ]);
        $this->markAsQueued($datum);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subMinutes(11)]);
        Queue::fake();

        $this->artisan('kpi:ai:queue-pending', ['--recover-stale' => true])
            ->expectsOutput('AI navbatiga qo‘yildi: 1')
            ->assertSuccessful();
        $this->artisan('kpi:ai:queue-pending', ['--recover-stale' => true])
            ->expectsOutput('AI navbatiga qo‘yildi: 0')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
            'message' => 'Yo‘qolgan AI job avtomatik qayta navbatga qo‘yildi.',
        ]);
    }

    public function test_recovery_does_not_requeue_a_resource_assigned_to_a_human_reviewer(): void
    {
        config()->set('queue.default', 'database');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $datum = $this->createDatum(['reviewer_hemis_id' => 3172011004]);
        $this->markAsQueued($datum);
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'info',
            'message' => 'Inson tekshiruviga biriktirildi.',
            'message_type' => 'ai_human_review_assigned',
        ]);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subMinutes(11)]);
        Queue::fake();

        $this->artisan('kpi:ai:queue-pending', ['--recover-stale' => true])
            ->expectsOutput('AI navbatiga qo‘yildi: 0')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(3172011004, $datum->fresh()->reviewer_hemis_id);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
        ]);
    }

    public function test_ai_worker_loop_recovers_a_stale_orphan_without_the_scheduler(): void
    {
        config()->set('queue.default', 'database');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $datum = $this->createDatum();
        $this->markAsQueued($datum);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subMinutes(11)]);
        Queue::fake();

        event(new Looping('database', ProcessAiDatumEvaluation::QUEUE));
        event(new Looping('database', ProcessAiDatumEvaluation::QUEUE));

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
            'message' => 'Yo‘qolgan AI job avtomatik qayta navbatga qo‘yildi.',
        ]);
    }

    public function test_recovery_waits_while_the_real_ai_queue_is_not_empty(): void
    {
        config()->set('queue.default', 'database');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $datum = $this->createDatum();
        $this->markAsQueued($datum);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subMinutes(11)]);
        DB::table('jobs')->insert([
            'queue' => ProcessAiDatumEvaluation::QUEUE,
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        Queue::fake();

        $this->artisan('kpi:ai:queue-pending', ['--recover-stale' => true])
            ->expectsOutput('AI queue bo‘sh emas, yo‘qolgan joblarni tiklash keyingi tekshiruvgacha kutadi.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
        ]);
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

    public function test_diagnostic_command_prioritizes_the_latest_attempt_failure(): void
    {
        config()->set('gemini.api_key', 'configured-key');
        Cache::put('kpi:ai-worker:last-seen-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-at', now()->toIso8601String(), now()->addHour());
        Cache::put(
            'kpi:ai-worker:last-failure-reason',
            'AI xizmatiga kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri.',
            now()->addHour(),
        );

        $this->artisan('kpi:ai:diagnose')
            ->expectsOutputToContain('AI worker jobni oldi, lekin urinish xato bilan yakunlandi')
            ->expectsOutputToContain('kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri')
            ->assertSuccessful();
    }

    public function test_queue_exception_event_records_a_safe_attempt_failure(): void
    {
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('resolveName')
            ->once()
            ->andReturn(ProcessAiDatumEvaluation::class);
        $queueJob->shouldReceive('attempts')
            ->once()
            ->andReturn(1);

        event(new JobExceptionOccurred(
            'database',
            $queueJob,
            new RuntimeException('429 quota exceeded: secret details'),
        ));

        $this->assertSame(
            'AI xizmatining so‘rov limiti tugagan. Limit yangilanishi yoki tarif sozlamasi tekshirilishi kerak.',
            Cache::get('kpi:ai-worker:last-failure-reason'),
        );
        $this->assertNotNull(Cache::get('kpi:ai-worker:last-failure-at'));
        $this->assertSame(1, Cache::get('kpi:ai-worker:last-failure-attempt'));
    }

    public function test_depleted_gemini_credit_pauses_the_ai_queue(): void
    {
        $connection = (string) config('queue.default');
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('resolveName')
            ->once()
            ->andReturn(ProcessAiDatumEvaluation::class);
        $queueJob->shouldReceive('attempts')
            ->once()
            ->andReturn(1);
        $queueJob->shouldReceive('getQueue')
            ->once()
            ->andReturn(ProcessAiDatumEvaluation::QUEUE);

        event(new JobExceptionOccurred(
            $connection,
            $queueJob,
            new ErrorException([
                'code' => 429,
                'message' => 'Your prepayment credits are depleted. Add funds to continue.',
                'status' => 'RESOURCE_EXHAUSTED',
            ]),
        ));

        $this->assertTrue(Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE));
        $this->assertSame(
            'Gemini API oldindan to‘lov krediti tugagan. AI Studio billing hisobiga kredit qo‘shish kerak.',
            Cache::get('kpi:ai-worker:paused-reason'),
        );
        $this->assertNotNull(Cache::get('kpi:ai-worker:paused-at'));
    }

    public function test_temporary_gemini_rate_limit_does_not_pause_the_ai_queue(): void
    {
        $connection = (string) config('queue.default');
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('resolveName')
            ->once()
            ->andReturn(ProcessAiDatumEvaluation::class);
        $queueJob->shouldReceive('attempts')
            ->once()
            ->andReturn(1);

        event(new JobExceptionOccurred(
            $connection,
            $queueJob,
            new ErrorException([
                'code' => 429,
                'message' => 'Quota exceeded for requests per minute. Retry later.',
                'status' => 'RESOURCE_EXHAUSTED',
            ]),
        ));

        $this->assertFalse(Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE));
    }

    public function test_diagnostic_command_reports_a_paused_ai_queue_and_resume_command(): void
    {
        $connection = (string) config('queue.default');
        config()->set('gemini.api_key', 'configured-key');
        Queue::pause($connection, ProcessAiDatumEvaluation::QUEUE);

        $this->artisan('kpi:ai:diagnose')
            ->expectsOutputToContain('PAUZA')
            ->expectsOutputToContain(
                "php artisan queue:continue {$connection}:".ProcessAiDatumEvaluation::QUEUE,
            )
            ->assertSuccessful();
    }

    public function test_diagnostic_command_reports_intentionally_disabled_ai_before_queue_failure(): void
    {
        $connection = (string) config('queue.default');
        Option::setAiEvaluationsEnabled(false);
        Queue::pause($connection, ProcessAiDatumEvaluation::QUEUE);

        $this->artisan('kpi:ai:diagnose')
            ->expectsOutputToContain('Global AI sozlamasi')
            ->expectsOutputToContain('Sozlamalar menyusidan vaqtincha o\'chirilgan')
            ->doesntExpectOutputToContain('Gemini krediti tugagani sabab')
            ->assertSuccessful();
    }

    public function test_gemini_error_codes_are_mapped_without_exposing_raw_details(): void
    {
        $reason = app(DescribeAiFailure::class)->handle(new ErrorException([
            'code' => 404,
            'message' => 'models/secret-model was not found for API key secret-key',
            'status' => 'NOT_FOUND',
        ]));

        $this->assertSame(
            'Kriteriyada ko‘rsatilgan Gemini modeli topilmadi yoki ushbu API versiyasida ishlamaydi.',
            $reason,
        );
        $this->assertStringNotContainsString('secret', $reason);
    }

    public function test_depleted_prepayment_credit_has_a_specific_safe_message(): void
    {
        $reason = app(DescribeAiFailure::class)->handle(new ErrorException([
            'code' => 429,
            'message' => 'Your prepayment credits are depleted. Go to https://ai.studio/projects.',
            'status' => 'RESOURCE_EXHAUSTED',
        ]));

        $this->assertSame(
            'Gemini API oldindan to‘lov krediti tugagan. AI Studio billing hisobiga kredit qo‘shish kerak.',
            $reason,
        );
        $this->assertStringNotContainsString('https://', $reason);
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

    private function clearAiWorkerCache(): void
    {
        foreach ([
            'kpi:ai-worker:last-seen-at',
            'kpi:ai-worker:last-success-at',
            'kpi:ai-worker:last-failure-at',
            'kpi:ai-worker:last-failure-reason',
            'kpi:ai-worker:last-failure-datum-id',
            'kpi:ai-worker:last-failure-attempt',
            'kpi:ai-worker:paused-at',
            'kpi:ai-worker:paused-reason',
            'kpi:ai-worker:heartbeat-at',
            'kpi:ai-worker:heartbeat-throttle',
            'kpi:ai-queue:recovery-throttle',
        ] as $key) {
            Cache::forget($key);
        }

        Queue::resume((string) config('queue.default'), ProcessAiDatumEvaluation::QUEUE);
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
