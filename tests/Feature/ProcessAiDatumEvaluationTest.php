<?php

namespace Tests\Feature;

use App\Actions\RecalculateReportPoints;
use App\Data\AiEvaluationResult;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use App\Services\AiSubmissionEvaluator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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
            'kpi:ai-worker:last-failure-datum-id',
            'kpi:ai-worker:last-failure-attempt',
            'kpi:ai-worker:heartbeat-at',
            'kpi:ai-worker:heartbeat-throttle',
        ] as $key) {
            Cache::forget($key);
        }

        RateLimiter::clear(ProcessAiDatumEvaluation::RATE_LIMIT_KEY);
    }

    public function test_job_persists_a_valid_ai_result_and_history(): void
    {
        $datum = $this->createDatum();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult(
                'accepted',
                8.5,
                'Talablar bajarilgan.',
                authorCount: 4,
                pageCount: 160,
                receivedAmount: 12_500_000.50,
            ));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(8.5, $datum->point);
        $this->assertSame(4, $datum->author_count);
        $this->assertSame(160, $datum->page_count);
        $this->assertSame('12500000.50', $datum->received_amount);
        $this->assertSame('Talablar bajarilgan.', $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'success',
            'message_type' => 'ai_evaluation',
        ]);
        $this->assertNotNull(Cache::get('kpi:ai-worker:last-seen-at'));
        $this->assertNotNull(Cache::get('kpi:ai-worker:last-success-at'));
    }

    public function test_job_enforces_fixed_category_point_before_persisting_ai_result(): void
    {
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '3.1.12']);
        $datum->user->update(['degree' => 'no_degrees']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult('accepted', 1, 'Talablar bajarilgan.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(3.0, $datum->fresh()->point);
    }

    public function test_job_assigns_three_points_to_degree_holder_for_accepted_criterion_three_one_seven(): void
    {
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '3.1.7']);
        $datum->user->update(['degree' => 'hold_degrees']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult('accepted', 0.5, 'PhD ilmiy rahbarligi tasdiqlandi.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(3.0, $datum->fresh()->point);
    }

    public function test_job_ignores_gemini_point_and_assigns_criterion_three_one_eleven_point_from_user_category(): void
    {
        foreach (['hold_degrees' => 3.0, 'no_degrees' => 4.0, 'foreign_lang' => 4.0, 'physical' => 4.0] as $degree => $expectedPoint) {
            $datum = $this->createDatum();
            $datum->criterion->update(['code' => '3.1.11']);
            $datum->user->update(['degree' => $degree]);
            $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
            $evaluator->shouldReceive('evaluate')
                ->once()
                ->andReturn(new AiEvaluationResult('accepted', 1, 'Stipendiat va rahbarlik tasdiqlandi.'));
            $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
            $recalculateReportPoints->shouldReceive('handle')
                ->once()
                ->with(Mockery::type(Report::class));

            (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame($expectedPoint, $datum->fresh()->point);
        }
    }

    public function test_job_assigns_zero_when_criterion_three_one_eleven_is_not_accepted(): void
    {
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '3.1.11']);
        $datum->user->update(['degree' => 'no_degrees']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult('cancelled', 2, 'Stipendiatlik tasdiqlanmadi.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame('cancelled', $datum->fresh()->status);
        $this->assertSame(0.0, $datum->fresh()->point);
    }

    public function test_job_persists_professional_development_top_tier_and_server_point(): void
    {
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '2.1.5']);
        $datum->user->update(['degree' => 'no_degrees']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult(
                'accepted',
                2.25,
                'Top-101–300 tasdiqlandi.',
                universityTier: 'top_300',
            ));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(2.25, $datum->point);
        $this->assertSame('top_300', $datum->university_tier);
    }

    public function test_job_assigns_criterion_one_ten_point_from_user_category(): void
    {
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '1.10']);
        $datum->user->update(['degree' => 'physical']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult('accepted', 0, 'Master-klass tasdiqlandi.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(4.0, $datum->fresh()->point);
    }

    public function test_job_assigns_ai_human_review_result_to_global_reviewer_regardless_of_criterion(): void
    {
        $datum = $this->createDatum();
        User::factory()->create(['hemis_id' => 3172011004]);
        AiHumanReviewAssignment::query()->create([
            'hemis_id' => 3172011004,
            'active_slot' => 1,
            'assigned_at' => now(),
        ]);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $datum->criterion_id,
            'hemis_id' => User::factory()->create()->hemis_id,
            'criterion_code' => '1/'.$datum->criterion_id,
        ]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(AiEvaluationResult::checking('Hujjatni inson tekshirishi kerak.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertSame(3172011004, $datum->reviewer_hemis_id);
        $this->assertSame(Datum::PUBLIC_CHECKING_REASON, $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'warning',
            'message_type' => 'ai_evaluation',
            'message' => 'Hujjatni inson tekshirishi kerak.',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'info',
            'message_type' => 'ai_human_review_assigned',
        ]);
    }

    public function test_job_assigns_criterion_specific_ai_human_reviewer_before_global_reviewer(): void
    {
        config()->set('kpi.ai_human_review_criterion_reviewers', [
            '1.10' => 3862011037,
        ]);
        $datum = $this->createDatum();
        $datum->criterion->update(['code' => '1.10']);
        AiHumanReviewAssignment::query()->create([
            'hemis_id' => 3172011004,
            'active_slot' => 1,
            'assigned_at' => now(),
        ]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(AiEvaluationResult::checking('Inson tekshiruvi kerak.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame(3862011037, $datum->fresh()->reviewer_hemis_id);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_human_review_assigned',
            'message' => 'AI inson tekshiruvi HEMIS ID 3862011037 mas’ulga biriktirildi.',
        ]);
    }

    public function test_job_rejects_a_clear_ai_failure_without_assigning_human_review(): void
    {
        $datum = $this->createDatum(['reviewer_hemis_id' => 3172011004]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(new AiEvaluationResult(
                'cancelled',
                0,
                'Majburiy nashr ruxsatnomasi hujjatda mavjud emas.',
            ));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))
            ->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame('cancelled', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertNull($datum->reviewer_hemis_id);
        $this->assertSame(
            'Majburiy nashr ruxsatnomasi hujjatda mavjud emas.',
            $datum->reason,
        );
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'error',
            'message_type' => 'ai_evaluation',
            'message' => 'Majburiy nashr ruxsatnomasi hujjatda mavjud emas.',
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_human_review_assigned',
        ]);
    }

    public function test_job_keeps_human_review_result_unassigned_when_global_reviewer_is_not_configured(): void
    {
        $datum = $this->createDatum();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(AiEvaluationResult::checking('Hujjatni inson tekshirishi kerak.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertNull($datum->fresh()->reviewer_hemis_id);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'warning',
            'message_type' => 'ai_human_review_unassigned',
        ]);
    }

    public function test_job_does_not_overwrite_a_submission_already_reviewed(): void
    {
        $datum = $this->createDatum(['status' => 'accepted', 'point' => 4]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame(4.0, $datum->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    public function test_job_retry_recalculates_points_for_an_already_persisted_ai_result(): void
    {
        $datum = $this->createDatum(['status' => 'accepted', 'point' => 4]);
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'success',
            'message' => 'AI tekshiruvi yakunlangan.',
            'message_type' => 'ai_evaluation',
        ]);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);

        $this->assertSame(4.0, $datum->fresh()->point);
        $this->assertDatabaseCount('datum_histories', 1);
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
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id, $originalCriterionId))
            ->handle($evaluator, $recalculateReportPoints);

        $datum->refresh();
        $this->assertSame($targetCriterion->id, $datum->criterion_id);
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_evaluation',
        ]);
    }

    public function test_job_processes_its_own_eligible_resource_without_dispatching_another_job(): void
    {
        $oldestDatum = $this->createDatum();
        $newerDatum = $this->createDatum();
        $this->markAsSubmitted($oldestDatum);
        $this->markAsSubmitted($newerDatum);
        Queue::fake();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::on(
                fn (Datum $datum): bool => $datum->is($newerDatum),
            ))
            ->andReturn(new AiEvaluationResult('accepted', 5, 'Jobga tegishli resurs tekshirildi.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(Report::class));

        $newerJob = (new ProcessAiDatumEvaluation($newerDatum->id, $newerDatum->criterion_id))
            ->withFakeQueueInteractions();
        $newerJob->handle($evaluator, $recalculateReportPoints);

        $newerJob->assertNotReleased();
        Queue::assertNothingPushed();
        $this->assertSame('checking', $oldestDatum->fresh()->status);
        $this->assertSame('accepted', $newerDatum->fresh()->status);
    }

    public function test_ai_job_serializes_gemini_evaluations_without_rate_limiting_stale_jobs(): void
    {
        $middleware = (new ProcessAiDatumEvaluation(1, 1))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame(ProcessAiDatumEvaluation::QUEUE, $middleware[0]->key);
        $this->assertSame(1, $middleware[0]->releaseAfter);
        $this->assertSame(90, $middleware[0]->expiresAfter);
        $this->assertTrue($middleware[0]->shareKey);
    }

    public function test_rate_limited_eligible_job_is_released_without_calling_gemini_or_refreshing_heartbeat(): void
    {
        config()->set('kpi.ai_requests_per_minute', 1);
        $datum = $this->createDatum();
        RateLimiter::hit(ProcessAiDatumEvaluation::RATE_LIMIT_KEY, 60);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');
        $job = (new ProcessAiDatumEvaluation($datum->id, $datum->criterion_id))
            ->withFakeQueueInteractions();

        $job->handle($evaluator, $recalculateReportPoints);

        $job->assertReleased();
        $this->assertNull(Cache::get('kpi:ai-worker:last-seen-at'));
        $this->assertSame('checking', $datum->fresh()->status);
    }

    public function test_disabled_ai_releases_job_without_calling_gemini(): void
    {
        Option::setAiEvaluationsEnabled(false);
        $datum = $this->createDatum();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');
        $job = (new ProcessAiDatumEvaluation($datum->id, $datum->criterion_id))
            ->withFakeQueueInteractions();

        $job->handle($evaluator, $recalculateReportPoints);

        $job->assertReleased(60);
        $this->assertSame(0, RateLimiter::attempts(ProcessAiDatumEvaluation::RATE_LIMIT_KEY));
        $this->assertNull(Cache::get('kpi:ai-worker:last-seen-at'));
        $this->assertSame('checking', $datum->fresh()->status);
    }

    public function test_completed_duplicate_job_does_not_consume_rate_limit_or_refresh_heartbeat(): void
    {
        $datum = $this->createDatum(['status' => 'accepted']);
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldNotReceive('evaluate');
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        (new ProcessAiDatumEvaluation($datum->id, $datum->criterion_id))
            ->handle($evaluator, $recalculateReportPoints);

        $this->assertSame(0, RateLimiter::attempts(ProcessAiDatumEvaluation::RATE_LIMIT_KEY));
        $this->assertNull(Cache::get('kpi:ai-worker:last-seen-at'));
    }

    public function test_failed_job_leaves_submission_checking_without_human_reviewer_assignment(): void
    {
        $datum = $this->createDatum(['reviewer_hemis_id' => 3172011004]);

        (new ProcessAiDatumEvaluation($datum->id))->failed(new RuntimeException('Network error'));

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertNull($datum->reviewer_hemis_id);
        $this->assertSame(Datum::PUBLIC_CHECKING_REASON, $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'warning',
            'message_type' => 'ai_failed',
            'message' => 'AI xizmatiga tarmoq orqali ulanib bo‘lmadi.',
        ]);
    }

    public function test_job_caches_the_datum_id_when_evaluation_throws(): void
    {
        $datum = $this->createDatum();
        $evaluator = Mockery::mock(AiSubmissionEvaluator::class);
        $evaluator->shouldReceive('evaluate')
            ->once()
            ->andThrow(new RuntimeException('Gemini request failed.'));
        $recalculateReportPoints = Mockery::mock(RecalculateReportPoints::class);
        $recalculateReportPoints->shouldNotReceive('handle');

        $this->expectException(RuntimeException::class);

        try {
            (new ProcessAiDatumEvaluation($datum->id))->handle($evaluator, $recalculateReportPoints);
        } finally {
            $this->assertSame(
                $datum->id,
                Cache::get('kpi:ai-worker:last-failure-datum-id'),
            );
        }
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
            ->assertSee('E’tibor kerak')
            ->assertSee(route('ai-status.index'));

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlayapti, ammo xatolar bor')
            ->assertSee('Oxirgi AI xabari')
            ->assertSee('2 ta resurs AI xatosidan keyin inson ko‘rigini kutmoqda.')
            ->assertDontSee('Hujjat ID:')
            ->assertSee('AI tekshiruv statistikasi')
            ->assertSee('AI TEKSHIRGAN')
            ->assertSee('NAVBATDA')
            ->assertSee('E’TIBOR TALAB QILADI')
            ->assertSee('QABUL QILINGAN')
            ->assertSee('RAD ETILGAN')
            ->assertSee('INSON TEKSHIRUVIDA')
            ->assertSee('AI XATOSI')
            ->assertDontSee('AI xizmatining urinishlari')
            ->assertDontSee('Hisoblash tartibi')
            ->assertDontSee('Hisobotlar kesimida AI holati')
            ->assertSee('Oxirgi 3 ta AI tekshiruvi')
            ->assertSee('Uchinchi tekshiruv')
            ->assertSee('Birinchi tekshiruv')
            ->assertDontSee('Worker va real queue holati')
            ->assertDontSee('Oxirgi worker heartbeat')
            ->assertDontSee('Qo‘lda tekshiriladigan kriteriya')
            ->assertViewMissing('statistics')
            ->assertViewHas('recentChecks', fn (array $recentChecks): bool => count($recentChecks) === 2
                && $recentChecks[0]['message'] === 'Uchinchi tekshiruv'
                && $recentChecks[1]['message'] === 'Birinchi tekshiruv')
            ->assertViewMissing('reportStatistics')
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 8
                && $statistics['evaluated'] === 4
                && $statistics['accepted'] === 3
                && $statistics['cancelled'] === 1
                && $statistics['human_review'] === 0
                && $statistics['waiting'] === 2
                && $statistics['failed_pending'] === 2
                && $statistics['legacy_untracked'] === 0
                && $statistics['evaluation_rate'] === 50.0)
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'degraded'
                && $status['reason'] === '2 ta resurs AI xatosidan keyin inson ko‘rigini kutmoqda.'
                && $status['last_message'] === '2 ta resurs AI xatosidan keyin inson ko‘rigini kutmoqda.'
                && $status['last_message_type'] === 'failure'
                && $status['last_message_datum_id'] === null
                && $status['last_message_at'] !== null
                && $status['pending_resources'] === 4
                && $status['waiting_resources'] === 2
                && $status['failed_pending_resources'] === 2
                && $status['legacy_untracked_resources'] === 0);
    }

    public function test_ai_status_shows_only_the_latest_three_checks_with_links_and_escaped_conclusions(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $oldest = $this->createAiHistory('ai_evaluation', 'success', 'Eng eski xulosa');
        $second = $this->createAiHistory('ai_evaluation', 'success', 'Ikkinchi xulosa');
        $third = $this->createAiHistory('ai_evaluation', 'success', 'Uchinchi xulosa');
        $pendingDatum = $this->createDatum(['status' => 'checking']);
        $newest = $pendingDatum->histories()->create([
            'user_id' => $pendingDatum->user_id,
            'type' => 'warning',
            'message' => '<script>alert("unsafe")</script> Eng yangi xulosa',
            'message_type' => 'ai_evaluation',
        ]);
        $oldest->update(['created_at' => now()->subMinutes(4)]);
        $second->update(['created_at' => now()->subMinutes(3)]);
        $third->update(['created_at' => now()->subMinutes(2)]);
        $newest->update(['created_at' => now()->subMinute()]);

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('Oxirgi 3 ta AI tekshiruvi')
            ->assertSee(route('upload.details', $pendingDatum))
            ->assertSee(route('upload.details', $third->datum_id))
            ->assertSee(route('upload.details', $second->datum_id))
            ->assertDontSee(route('upload.details', $oldest->datum_id))
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt; Eng yangi xulosa', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false)
            ->assertViewHas('recentChecks', fn (array $recentChecks): bool => array_column($recentChecks, 'datum_id') === [
                $pendingDatum->id,
                $third->datum_id,
                $second->datum_id,
            ]);

        $this->actingAs($statusViewer)
            ->get(route('upload.details', $pendingDatum))
            ->assertOk();
    }

    public function test_stale_audit_queue_without_a_real_job_is_shown_as_recovering(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        config()->set('queue.default', 'database');
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
            ->assertSee('Tiklanmoqda')
            ->assertSee('Tizim uni avtomatik qayta yuboradi.');

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('Navbat qayta tiklanmoqda')
            ->assertSee('Joriy holat')
            ->assertSee('Tizim uni avtomatik qayta yuboradi.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'recovering'
                && $status['waiting_resources'] === 1
                && $status['queue_jobs'] === 0
                && $status['orphaned_resources'] === 1
                && $status['last_message_type'] === 'status'
                && $status['oldest_waiting_at'] !== null
                && $status['oldest_waiting_at']->lte(now()->subMinutes(10))
                && str_contains((string) $status['reason'], 'avtomatik'));
    }

    public function test_recent_worker_heartbeat_keeps_an_old_backlog_in_processing_state(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('kpi.ai_queue_stale_after_minutes', 10);
        config()->set('queue.default', 'database');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum(['status' => 'checking']);
        $this->markAsSubmitted($datum);
        $datum->histories()
            ->where('message_type', 'submission_created')
            ->update(['created_at' => now()->subHour()]);
        ProcessAiDatumEvaluation::dispatch($datum->id, $datum->criterion_id);
        Cache::put('kpi:ai-worker:heartbeat-at', now()->toIso8601String(), now()->addHour());

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('Tekshiruv davom etmoqda')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'processing'
                && $status['worker_heartbeat_at'] !== null
                && $status['worker_is_active']
                && $status['queue_jobs'] === 1
                && $status['waiting_resources'] === 1);
    }

    public function test_idle_worker_heartbeat_is_shown_as_waiting_for_a_new_resource(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('queue.default', 'database');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);

        event(new Looping('database', ProcessAiDatumEvaluation::QUEUE));

        $this->assertNotNull(Cache::get('kpi:ai-worker:heartbeat-at'));

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('Navbat bo‘sh')
            ->assertSee('Barcha yuborilgan resurslar ko‘rib chiqilgan.')
            ->assertDontSee('FAOL')
            ->assertDontSee('heartbeat')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'idle'
                && $status['worker_is_active']
                && $status['queue_jobs'] === 0
                && $status['processing_jobs'] === 0
                && $status['orphaned_resources'] === 0);
    }

    public function test_non_ai_worker_loop_does_not_create_an_ai_heartbeat(): void
    {
        event(new Looping('database', 'default'));

        $this->assertNull(Cache::get('kpi:ai-worker:heartbeat-at'));
    }

    public function test_real_queue_with_no_worker_heartbeat_is_shown_as_unavailable(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('queue.default', 'database');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum();
        $this->markAsSubmitted($datum);
        ProcessAiDatumEvaluation::dispatch($datum->id, $datum->criterion_id);

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI ishlamayapti')
            ->assertSee('Navbatda 1 ta vazifa bor, ammo avtomatik tekshiruv hozir javob bermayapti.')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unavailable'
                && ! $status['worker_is_active']
                && $status['queue_jobs'] === 1
                && $status['last_message_type'] === 'status');
    }

    public function test_retryable_attempt_failure_with_a_live_worker_is_shown_as_recovering(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        config()->set('queue.default', 'database');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum();
        $this->markAsSubmitted($datum);
        ProcessAiDatumEvaluation::dispatch($datum->id, $datum->criterion_id);
        Cache::put('kpi:ai-worker:heartbeat-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-datum-id', $datum->id, now()->addHour());
        Cache::put(
            'kpi:ai-worker:last-failure-reason',
            'AI xizmatidan belgilangan vaqt ichida javob kelmadi.',
            now()->addHour(),
        );

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('Navbat qayta tiklanmoqda')
            ->assertSee('Tizim avtomatik qayta urinadi')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'recovering'
                && $status['worker_is_active']
                && $status['queue_jobs'] === 1
                && $status['last_message_type'] === 'status'
                && $status['last_message_datum_id'] === $datum->id);
    }

    public function test_latest_worker_attempt_failure_is_shown_immediately(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $datum = $this->createDatum();
        Cache::put('kpi:ai-worker:last-seen-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-at', now()->toIso8601String(), now()->addHour());
        Cache::put('kpi:ai-worker:last-failure-datum-id', $datum->id, now()->addHour());
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
            ->assertSee('Hujjat ID:')
            ->assertSee((string) $datum->id)
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unavailable'
                && $status['last_message_datum_id'] === $datum->id
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
            ->assertSee('Tekshiruv davom etmoqda')
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
            ->assertSee('1 ta eski resursda AI navbat tarixi')
            ->assertDontSee('heartbeat')
            ->assertViewHas('status', fn (array $status): bool => $status['state'] === 'unknown'
                && $status['waiting_resources'] === 0
                && $status['legacy_untracked_resources'] === 1
                && $status['oldest_waiting_at'] === null
                && str_contains((string) $status['reason'], 'navbat tarixi mavjud emas'))
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

        $requeuedAfterEvaluation = $this->createDatum(['status' => 'checking']);
        $requeuedAfterEvaluation->histories()->createMany([
            [
                'user_id' => $requeuedAfterEvaluation->user_id,
                'type' => 'warning',
                'message' => 'Inson tekshiruviga yuborilgan AI natijasi.',
                'message_type' => 'ai_evaluation',
            ],
            [
                'user_id' => $requeuedAfterEvaluation->user_id,
                'type' => 'info',
                'message' => 'Qayta AI navbatiga qo‘yildi.',
                'message_type' => 'ai_queued',
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
            ->assertViewHas('resourceStatistics', fn (array $statistics): bool => $statistics['total'] === 7
                && $statistics['evaluated'] === 4
                && $statistics['accepted'] === 2
                && $statistics['cancelled'] === 1
                && $statistics['human_review'] === 1
                && $statistics['waiting'] === 2
                && $statistics['failed_pending'] === 1
                && $statistics['legacy_untracked'] === 0
                && $statistics['evaluation_rate'] === 57.1
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
            ->assertSee('AI tekshiruv natijalari hali mavjud emas.')
            ->assertViewHas('recentChecks', [])
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
