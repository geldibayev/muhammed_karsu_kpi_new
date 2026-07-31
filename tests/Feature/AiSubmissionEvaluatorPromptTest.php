<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Observance;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use App\Services\AiAuthorPointDistributor;
use App\Services\AiSubmissionEvaluator;
use App\Services\GeminiFileMimeTypeResolver;
use Gemini\Data\Blob;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\MimeType;
use Gemini\Exceptions\ErrorException;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiSubmissionEvaluatorPromptTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_request_contains_trusted_context_and_detects_jpeg_from_stored_bytes(): void
    {
        $this->travelTo(Carbon::parse('2026-07-30 12:00:00', 'Asia/Tashkent'));
        Storage::fake('local');
        $image = UploadedFile::fake()->image('proof.jpg', 10, 10);
        Storage::disk('local')->put('proof.jpg', $image->getContent());

        $user = User::factory()->create();
        Evaluation::query()->create([
            'code' => $user->degree,
            'name' => ['uz' => 'Test toifasi'],
            'status' => '1',
        ]);
        $year = Year::query()->create([
            'name' => '2025',
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => '2025-yil KPI hisoboti'],
            'status' => '1',
        ]);
        Observance::query()->create([
            'code' => 'last3years',
            'name' => ['uz' => 'Oxirgi 3 yilda'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Malaka oshirish'],
            'report_id' => $report->id,
            'observation' => 'last3years',
            'upload' => '1',
            'status' => '1',
            'checking' => 'ai',
            'ai_prompt' => 'Malaka oshirish hujjati sanasini tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => $user->degree,
            'has' => '1',
            'score' => 10,
        ]);
        $datum = Datum::query()->create([
            'name' => 'proof.jpg',
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'proof.jpg',
                'mime' => 'application/pdf',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'year_id' => $year->id,
            'status' => 'checking',
            'point' => 0,
        ]);

        $gemini = Gemini::fake([
            GenerateContentResponse::fake([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'status' => 'checking',
                                'point' => 0,
                                'reason' => 'Test javobi.',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = (new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
        ))->evaluate($datum);

        $this->assertSame('checking', $result->status);
        Gemini::assertSent(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $contentParts = $parameters[0] ?? null;
                $prompt = is_array($contentParts) ? ($contentParts[0] ?? null) : null;
                $file = is_array($contentParts) ? ($contentParts[1] ?? null) : null;

                return $method === 'generateContent'
                    && is_string($prompt)
                    && $file instanceof Blob
                    && $file->mimeType === MimeType::IMAGE_JPEG
                    && str_contains($prompt, '"current_date_iso":"2026-07-30"')
                    && str_contains($prompt, '"current_date_display":"30.07.2026"')
                    && str_contains($prompt, '"last_three_years_start_iso":"2023-07-30"')
                    && str_contains($prompt, '"timezone":"Asia/Tashkent"')
                    && str_contains($prompt, '"submission_year":{"id":')
                    && str_contains($prompt, '"name":"2025"')
                    && str_contains($prompt, '"report_period":{"id":')
                    && str_contains($prompt, '"name":"2025-yil KPI hisoboti"')
                    && str_contains($prompt, '"criterion_period_rule":{"code":"last3years"')
                    && str_contains($prompt, "tugash sanasi current_date_iso ga teng yoki undan oldin bo'lsa")
                    && str_contains($prompt, 'cancelled emas, checking statusini')
                    && str_contains($prompt, 'QAROR USTUVORLIGI:')
                    && str_contains($prompt, 'checking emas, cancelled statusini')
                    && str_contains($prompt, 'checking statusi bilan aniq rad etish sababini birga qaytarmang');
            },
        );
        $gemini->assertFunctionCalled(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $generationConfig = $parameters[0] ?? null;
                $responseSchema = $generationConfig instanceof GenerationConfig
                    ? $generationConfig->responseSchema?->toArray()
                    : null;
                $reasonSchema = data_get($responseSchema, 'properties.reason');

                return $method === 'withGenerationConfig'
                    && is_array($reasonSchema)
                    && $reasonSchema === ['type' => 'STRING'];
            },
        );
    }

    public function test_document_without_pages_is_sent_to_human_review_without_throwing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('empty.pdf', "%PDF-1.4\n% empty test document");

        $user = User::factory()->create();
        Evaluation::query()->create([
            'code' => $user->degree,
            'name' => ['uz' => 'Test toifasi'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'AI mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'checking' => 'ai',
            'ai_prompt' => 'Hujjatni tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => $user->degree,
            'has' => '1',
            'score' => 10,
        ]);
        $datum = Datum::query()->create([
            'name' => 'empty.pdf',
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'empty.pdf',
                'mime' => 'application/pdf',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ]);

        Gemini::fake([
            new ErrorException([
                'code' => 400,
                'message' => 'The document has no pages.',
                'status' => 'INVALID_ARGUMENT',
            ]),
        ]);

        $result = (new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
        ))->evaluate($datum);

        $this->assertSame('checking', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertSame(DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON, $result->reason);
    }
}
