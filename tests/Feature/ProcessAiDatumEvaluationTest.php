<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Report;
use App\Models\User;
use App\Services\AiSubmissionEvaluator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessAiDatumEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'kpi:ai-worker:last-seen-at',
            'kpi:ai-worker:last-success-at',
            'kpi:ai-worker:last-failure-at',
            'kpi:ai-worker:last-failure-reason',
            'kpi:ai-worker:last-failure-attempt',
        ] as $key) {
            Cache::forget($key);
        }
    }

    public function test_job_persists_a_valid_ai_result_and_history(): void
    {
        $datum = $this->createDatum();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult('accepted', 8.5, 'Talablar bajarilgan.'));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator);

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(8.5, $datum->point);
        $this->assertSame('Talablar bajarilgan.', $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'success',
            'message_type' => 'ai_evaluation',
        ]);
        $this->assertNotNull(Cache::get('kpi:ai-worker:last-success-at'));
    }

    public function test_job_does_not_overwrite_a_submission_already_reviewed(): void
    {
        $datum = $this->createDatum(['status' => 'accepted', 'point' => 4]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator);

        $this->assertSame(4.0, $datum->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    public function test_job_does_not_write_an_old_criterion_result_after_a_transfer(): void
    {
        $datum = $this->createDatum();
        $originalCriterionId = $datum->criterion_id;
        $targetCriterion = Criterion::query()->create([
            'name' => ['uz' => 'Yangi mezon'],
            'report_id' => $datum->criterion->report_id,
            'upload' => '1',
            'status' => '1',
            'checking' => 'ai',
            'ai_prompt' => 'Yangi mezon bo‘yicha tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturnUsing(function () use ($datum, $targetCriterion): AiEvaluationResult {
                $datum->update(['criterion_id' => $targetCriterion->id]);

                return new AiEvaluationResult('accepted', 8.5, 'Eski mezon natijasi.');
            });

        (new ProcessAiDatumEvaluation($datum->id, $originalCriterionId))->handle($evaluator);

        $datum->refresh();
        $this->assertSame($targetCriterion->id, $datum->criterion_id);
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_evaluation',
        ]);
    }

    public function test_ai_job_uses_the_gemini_rate_limiter(): void
    {
        $middleware = (new ProcessAiDatumEvaluation(1, 1))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
    }

    public function test_failed_job_leaves_submission_for_human_review(): void
    {
        $datum = $this->createDatum();

        (new ProcessAiDatumEvaluation($datum->id))->failed(new RuntimeException('Network error'));

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertStringContainsString('tarmoq orqali', $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'warning',
            'message_type' => 'ai_failed',
            'message' => 'AI xizmatiga tarmoq orqali ulanib bo‘lmadi.',
        ]);
    }

    public function test_configured_hemis_user_sees_ai_status_dashboard_statistics_and_latest_checks(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $this->createAiHistory('ai_evaluation', 'success', 'Birinchi tekshiruv');
        $this->createAiHistory('ai_failed', 'warning', 'Ikkinchi xato');
        $this->createAiHistory('ai_evaluation', 'success', 'Uchinchi tekshiruv');
        $this->createAiHistory('ai_failed', 'warning', 'To‘rtinchi xato');
        $this->markAsSubmitted($this->createDatum(['status' => 'received']));
        $this->markAsSubmitted($this->createDatum(['status' => 'checking']));
        $this->createDatum(['status' => 'accepted']);
        $this->createDatum(['status' => 'cancelled']);
        $this->createDatum(['status' => 'deleted']);
        $this->createAiHistory(
            'ai_evaluation',
            'success',
            'Qo‘lda tekshiriladigan kriteriya',
            checking: 'manual',
        );

        $this->actingAs($statusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('AI holati')
            ->assertSee('Ishlamayapti')
            ->assertSee(route('ai-status.index'));

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlamayapti')
            ->assertSee('Oxirgi AI xabari')
            ->assertSee('To‘rtinchi xato')
            ->assertSee('Resurslar holati')
            ->assertSee('TEKSHIRILGAN')
            ->assertSee('NAVBATDA')
            ->assertSee('XATO')
            ->assertSee('NAVBATGA QO‘YILMAGAN')
            ->assertDontSee('AI xizmatining urinishlari')
            ->assertDontSee('Hisoblash tartibi')
            ->assertDontSee('Hisobotlar kesimida AI holati')
            ->assertDontSee('Oxirgi 3 ta AI tekshiruvi')
            ->assertDontSee('Worker heartbeat')
            ->assertDontSee('Qo‘lda tekshiriladigan kriteriya')
            ->assertViewMissing('statistics')
            ->assertViewMissing('recentChecks')
            ->assertViewMissing('reportStatistics')
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 8
                && $statistics['evaluated'] === 4
                && $statistics['waiting'] === 2
                && $statistics['failed_pending'] === 2
                && $statistics['legacy_untracked'] === 0
                && $statistics['evaluation_rate'] === 50.0)
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unavailable'
                && $status['reason'] === 'To‘rtinchi xato'
                && $status['last_message'] === 'To‘rtinchi xato'
                && $status['last_message_type'] === 'failure'
                && $status['last_message_at'] !== null
                && $status['pending_resources'] === 4
                && $status['waiting_resources'] === 2
                && $status['failed_pending_resources'] === 2
                && $status['legacy_untracked_resources'] === 0);
    }

    public function test_stale_ai_queue_is_shown_as_unavailable_with_a_clear_reason(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum(['status' => 'checking']);
        $this->markAsSubmitted($datum);
        Datum::query()
            ->whereKey($datum)
            ->update(['created_at' => now()->subMinutes(11)]);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subMinutes(11)]);

        $this->actingAs($statusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Ishlamayapti')
            ->assertSee('AI worker heartbeat hali qayd etilmagan.');

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlamayapti')
            ->assertSee('Oxirgi AI xabari')
            ->assertSee('AI worker heartbeat hali qayd etilmagan.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unavailable'
                && $status['waiting_resources'] === 1
                && $status['oldest_waiting_at'] !== null
                && $status['oldest_waiting_at']->lte(now()->subMinutes(10))
                && str_contains((string) $status['reason'], 'heartbeat'));
    }

    public function test_recent_worker_heartbeat_keeps_an_old_backlog_in_processing_state(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum(['status' => 'checking']);
        $this->markAsSubmitted($datum);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subHour()]);
        Cache::put('kpi:ai-worker:last-seen-at', now()->toIso8601String(), now()->addHour());

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI navbatni ishlamoqda')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'processing'
                && $status['worker_last_seen_at'] !== null
                && $status['waiting_resources'] === 1);
    }

    public function test_latest_worker_attempt_failure_is_shown_immediately(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        Cache::put('kpi:ai-worker:last-seen-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-at', now()->toIso8601String(), now()->addHour());
        Cache::put(
            'kpi:ai-worker:last-failure-reason',
            'AI xizmatidan belgilangan vaqt ichida javob kelmadi.',
            now()->addHour(),
        );

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlamayapti')
            ->assertSee('Oxirgi AI xabari')
            ->assertSee('AI xizmatidan belgilangan vaqt ichida javob kelmadi.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unavailable'
                && str_contains((string) $status['reason'], 'javob kelmadi'));
    }

    public function test_latest_ai_message_is_escaped_in_the_status_card(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        Cache::put('kpi:ai-worker:last-failure-at', now()->toIso8601String(), now()->addHour());
        Cache::put(
            'kpi:ai-worker:last-failure-reason',
            '<script>alert("unsafe")</script>',
            now()->addHour(),
        );

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);
    }

    public function test_fresh_ai_queue_is_shown_as_processing(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $this->markAsSubmitted($this->createDatum(['status' => 'checking']));

        $this->actingAs($statusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Navbatda');

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI navbatni ishlamoqda')
            ->assertSee('1 ta resurs AI tekshiruv navbatida.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'processing'
                && $status['waiting_resources'] === 1
                && $status['pending_resources'] === 1);
    }

    public function test_legacy_pending_resources_do_not_report_the_ai_worker_as_unavailable(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum(['status' => 'checking']);
        Datum::query()
            ->whereKey($datum)
            ->update(['created_at' => now()->subYear()]);

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('1 ta eski resursda AI navbat auditi')
            ->assertDontSee('AI worker heartbeat hali qayd etilmagan.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unknown'
                && $status['waiting_resources'] === 0
                && $status['legacy_untracked_resources'] === 1
                && $status['oldest_waiting_at'] === null
                && str_contains((string) $status['reason'], 'joriy worker holatini bildirmaydi'))
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 1
                && $statistics['waiting'] === 0
                && $statistics['legacy_untracked'] === 1);
    }

    public function test_latest_successful_ai_check_is_shown_as_operational(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $this->createAiHistory('ai_evaluation', 'success', 'AI tekshiruvi muvaffaqiyatli.');

        $this->actingAs($statusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Ishlayapti')
            ->assertSee('badge-success');

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlayapti')
            ->assertSee('Oxirgi tekshiruv')
            ->assertSee('AI tekshiruvi muvaffaqiyatli.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'operational'
                && $status['reason'] === null
                && $status['last_message'] === 'AI tekshiruvi muvaffaqiyatli.'
                && $status['last_message_type'] === 'success'
                && $status['last_message_at'] !== null
                && $status['pending_resources'] === 0);
    }

    public function test_ai_resource_statistics_are_unique_and_partition_every_ai_resource(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);

        $humanReview = $this->createDatum(['status' => 'checking']);
        $humanReview->histories()->createMany([
            [
                'user_id' => $humanReview->user_id,
                'type' => 'warning',
                'message' => 'Birinchi AI natijasi.',
                'message_type' => 'ai_evaluation',
            ],
            [
                'user_id' => $humanReview->user_id,
                'type' => 'warning',
                'message' => 'Takroriy AI natijasi.',
                'message_type' => 'ai_evaluation',
            ],
        ]);

        $recoveredAfterFailure = $this->createDatum(['status' => 'accepted']);
        $recoveredAfterFailure->histories()->createMany([
            [
                'user_id' => $recoveredAfterFailure->user_id,
                'type' => 'warning',
                'message' => 'Vaqtinchalik xato.',
                'message_type' => 'ai_failed',
            ],
            [
                'user_id' => $recoveredAfterFailure->user_id,
                'type' => 'success',
                'message' => 'Keyingi urinish muvaffaqiyatli.',
                'message_type' => 'ai_evaluation',
            ],
        ]);

        $this->markAsSubmitted($this->createDatum(['status' => 'received']));
        $this->createAiHistory('ai_failed', 'warning', 'Tekshirishda xato.');
        $this->createDatum(['status' => 'accepted']);
        $this->createDatum(['status' => 'cancelled']);
        $this->createDatum(['status' => 'deleted']);
        $this->createDatum(['status' => 'checking'], 'manual');

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 6
                && $statistics['evaluated'] === 4
                && $statistics['waiting'] === 1
                && $statistics['failed_pending'] === 1
                && $statistics['legacy_untracked'] === 0
                && $statistics['evaluation_rate'] === 66.7
                && $statistics['total'] === (
                    $statistics['evaluated']
                    + $statistics['waiting']
                    + $statistics['failed_pending']
                    + $statistics['legacy_untracked']
                ));
    }

    public function test_ai_status_dashboard_is_hidden_from_other_users_and_guests(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $otherUser = User::factory()->create();

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI holati aniqlanmagan')
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 0
                && $statistics['evaluated'] === 0
                && $statistics['waiting'] === 0
                && $statistics['failed_pending'] === 0
                && $statistics['legacy_untracked'] === 0
                && $statistics['evaluation_rate'] === 0.0)
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unknown'
                && $status['reason'] === null
                && $status['pending_resources'] === 0
                && $status['waiting_resources'] === 0
                && $status['failed_pending_resources'] === 0
                && $status['legacy_untracked_resources'] === 0
                && $status['last_message'] === null);

        $this->actingAs($otherUser)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('ai-status.index'));
        $this->actingAs($otherUser)
            ->get(route('ai-status.index'))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('ai-status.index'))
            ->assertRedirect(route('login'));
    }

    private function createAiHistory(
        string $messageType,
        string $type,
        string $message,
        string $checking = 'ai',
    ): DatumHistory {
        $datum = $this->createDatum([
            'status' => $messageType === 'ai_evaluation' ? 'accepted' : 'checking',
        ], $checking);

        return $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => $type,
            'message' => $message,
            'message_type' => $messageType,
        ]);
    }

    private function markAsSubmitted(Datum $datum): void
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
            'name' => 'proof.pdf',
            'material' => ['type' => 'file', 'disk' => 'local', 'path' => 'proof.pdf'],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ], $attributes));
    }
}
