<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use App\Services\AiSubmissionEvaluator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
