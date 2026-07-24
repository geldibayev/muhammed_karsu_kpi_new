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
use Illuminate\Support\Collection;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessAiDatumEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_failed_job_leaves_submission_for_human_review(): void
    {
        $datum = $this->createDatum();

        (new ProcessAiDatumEvaluation($datum->id))->failed(new RuntimeException('Network error'));

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertStringContainsString('Inson tekshiruvi', $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'type' => 'warning',
            'message_type' => 'ai_failed',
        ]);
    }

    public function test_configured_hemis_user_sees_ai_status_dashboard_statistics_and_latest_checks(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $firstCheck = $this->createAiHistory('ai_evaluation', 'success', 'Birinchi tekshiruv');
        $secondCheck = $this->createAiHistory('ai_failed', 'warning', 'Ikkinchi xato');
        $thirdCheck = $this->createAiHistory('ai_evaluation', 'success', 'Uchinchi tekshiruv');
        $fourthCheck = $this->createAiHistory('ai_failed', 'warning', 'To‘rtinchi xato');

        $this->actingAs($statusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('AI holati')
            ->assertSee(route('ai-status.index'));

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI tekshiruvchi ishlamayapti')
            ->assertSee('Umumiy AI tekshiruvlari')
            ->assertSee('Muvaffaqiyatli tekshiruvlar')
            ->assertSee('Xato yakunlangan tekshiruvlar')
            ->assertSee('Oxirgi muvaffaqiyatli tekshiruv')
            ->assertSee('Oxirgi xato')
            ->assertSee('Oxirgi 3 ta AI tekshiruvi')
            ->assertSeeInOrder(['To‘rtinchi xato', 'Uchinchi tekshiruv', 'Ikkinchi xato'])
            ->assertDontSee('Birinchi tekshiruv')
            ->assertViewHas('statistics', fn (array $statistics): bool => $statistics['total_checks'] === 4
                && $statistics['successful_checks'] === 2
                && $statistics['failed_checks'] === 2
                && $statistics['last_success_at'] !== null
                && $statistics['last_failure_at'] !== null)
            ->assertViewHas(
                'recentChecks',
                fn (Collection $checks): bool => $checks->pluck('id')->all() === [
                    $fourthCheck->id,
                    $thirdCheck->id,
                    $secondCheck->id,
                ] && ! $checks->contains('id', $firstCheck->id),
            );
    }

    public function test_ai_status_dashboard_is_hidden_from_other_users_and_guests(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $statusViewer = User::factory()->create(['hemis_id' => 3172011004]);
        $otherUser = User::factory()->create();

        $this->actingAs($statusViewer)
            ->get(route('ai-status.index'))
            ->assertOk()
            ->assertSee('AI tekshiruvchi statusi hali aniqlanmagan')
            ->assertViewHas('statistics', fn (array $statistics): bool => $statistics['total_checks'] === 0
                && $statistics['successful_checks'] === 0
                && $statistics['failed_checks'] === 0
                && $statistics['last_success_at'] === null
                && $statistics['last_failure_at'] === null);

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

    private function createAiHistory(string $messageType, string $type, string $message): DatumHistory
    {
        $datum = $this->createDatum();

        return $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => $type,
            'message' => $message,
            'message_type' => $messageType,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createDatum(array $attributes = []): Datum
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
            'checking' => 'ai',
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
