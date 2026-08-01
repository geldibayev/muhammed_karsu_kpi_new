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
use App\Services\AiResourceDatePolicy;
use App\Services\AiSubmissionEvaluator;
use App\Services\GeminiFileMimeTypeResolver;
use App\Services\GeminiUrlContextGateway;
use App\Services\OakArticleScoreCalculator;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSubmissionEvaluatorUrlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_url_is_evaluated_with_gemini_url_context(): void
    {
        $url = 'https://example.com/evidence.pdf';
        $datum = $this->createUrlDatum($url);
        $this->fakeUrlContext($this->urlPayload($url));

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(5.0, $result->point);
        $this->assertSame('2026-08-31', $result->resourceDate);
        Http::assertSent(function (Request $request) use ($url): bool {
            $prompt = data_get($request->data(), 'contents.0.parts.0.text');

            return $request->method() === 'POST'
                && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && data_get($request->data(), 'tools.0.url_context') !== null
                && data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json'
                && is_string($prompt)
                && str_contains($prompt, "TEKSHIRILADIGAN OMMAVIY URL: {$url}")
                && str_contains($prompt, 'Faqat URL Context vositasi orqali olingan mazmunni dalil sifatida ishlating');
        });
        Gemini::assertNothingSent();
    }

    public function test_url_is_cancelled_when_gemini_cannot_retrieve_its_content(): void
    {
        $datum = $this->createUrlDatum('https://example.com/private-evidence');
        $this->fakeUrlContext($this->urlPayload());

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('muvaffaqiyatli yuklanganini tasdiqlamadi', $result->reason);
    }

    public function test_paywalled_url_is_cancelled_with_an_explicit_reason(): void
    {
        $url = 'https://example.com/paywalled-evidence';
        $datum = $this->createUrlDatum($url);
        $this->fakeUrlContext($this->urlPayload($url, 'URL_RETRIEVAL_STATUS_PAYWALL'));

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('login yoki pulli obuna', $result->reason);
    }

    public function test_cross_domain_redirect_is_cancelled(): void
    {
        $datum = $this->createUrlDatum('https://example.com/evidence');
        $this->fakeUrlContext($this->urlPayload('https://malicious.example/evidence'));

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('boshqa domen', $result->reason);
    }

    public function test_rest_snake_case_url_metadata_is_supported(): void
    {
        $url = 'https://example.com/snake-case-evidence';
        $datum = $this->createUrlDatum($url);
        $payload = $this->urlPayload();
        $payload['candidates'][0]['url_context_metadata'] = [
            'url_metadata' => [[
                'retrieved_url' => $url,
                'url_retrieval_status' => 'URL_RETRIEVAL_STATUS_SUCCESS',
            ]],
        ];
        $this->fakeUrlContext($payload);

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('accepted', $result->status);
        $this->assertSame(5.0, $result->point);
    }

    public function test_private_url_is_cancelled_without_calling_gemini(): void
    {
        $datum = $this->createUrlDatum('http://127.0.0.1/internal-document');
        Gemini::fake();
        Http::preventStrayRequests();

        $result = $this->evaluator()->evaluate($datum);

        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('ommaviy internet manzili emas', $result->reason);
        Gemini::assertNothingSent();
        Http::assertNothingSent();
    }

    private function createUrlDatum(string $url): Datum
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

        return Datum::query()->create([
            'name' => $url,
            'material' => [
                'type' => 'url',
                'link' => $url,
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ]);
    }

    private function evaluator(): AiSubmissionEvaluator
    {
        return new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new OakArticleScoreCalculator,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
            new AiResourceDatePolicy,
            new GeminiUrlContextGateway,
            new PrintedEducationalLiteratureScoreCalculator,
        );
    }

    /** @param array<string, mixed> $payload */
    private function fakeUrlContext(array $payload): void
    {
        config()->set('gemini.api_key', 'test-api-key');
        config()->set('gemini.base_url', null);
        Gemini::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*' => Http::response($payload),
        ]);
    }

    /** @return array<string, mixed> */
    private function urlPayload(
        ?string $retrievedUrl = null,
        string $retrievalStatus = '',
    ): array {
        $candidate = [
            'content' => [
                'parts' => [[
                    'text' => json_encode([
                        'status' => 'accepted',
                        'point' => 5,
                        'resource_date' => '2026-08-31',
                        'reason' => 'URL resursi tasdiqlandi.',
                    ], JSON_THROW_ON_ERROR),
                ]],
                'role' => 'model',
            ],
        ];

        if ($retrievedUrl !== null) {
            $candidate['urlContextMetadata'] = [
                'urlMetadata' => [[
                    'retrievedUrl' => $retrievedUrl,
                    'urlRetrievalStatus' => $retrievalStatus !== ''
                        ? $retrievalStatus
                        : 'URL_RETRIEVAL_STATUS_SUCCESS',
                ]],
            ];
        }

        return [
            'candidates' => [[
                ...$candidate,
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 10,
                'totalTokenCount' => 20,
            ],
        ];
    }
}
