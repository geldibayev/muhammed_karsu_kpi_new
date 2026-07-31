<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Services\AiAuthorPointDistributor;
use App\Services\AiSubmissionEvaluator;
use App\Services\GeminiFileMimeTypeResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiSubmissionEvaluatorUrlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_url_evidence_is_left_for_human_review_without_calling_ai(): void
    {
        $user = User::factory()->create();
        Evaluation::query()->create([
            'code' => $user->degree,
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'URL mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'checking' => 'ai',
            'ai_prompt' => 'Havoladagi materialni tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => $user->degree,
            'has' => '1',
            'score' => 5,
        ]);
        $datum = Datum::query()->create([
            'name' => 'https://example.com/evidence',
            'material' => [
                'type' => 'url',
                'link' => 'https://example.com/evidence',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ]);

        $result = (new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
        ))->evaluate($datum);

        $this->assertSame('checking', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('inson tekshiruvi', $result->reason);
    }
}
