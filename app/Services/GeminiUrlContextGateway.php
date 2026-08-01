<?php

namespace App\Services;

use App\Data\GeminiUrlContextResponse;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GeminiUrlContextGateway
{
    public function generateContent(
        string $model,
        string $systemInstruction,
        GenerationConfig $generationConfig,
        string $prompt,
    ): GeminiUrlContextResponse {
        $response = $this->request()->post(
            $this->endpoint($model),
            [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'tools' => [[
                    'url_context' => (object) [],
                ]],
                'systemInstruction' => Content::parse($systemInstruction)->toArray(),
                'generationConfig' => $generationConfig->toArray(),
            ],
        )->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Gemini URL Context javobi JSON obyekt emas.');
        }

        $text = data_get($payload, 'candidates.0.content.parts.0.text');
        $urlMetadata = data_get($payload, 'candidates.0.urlContextMetadata.urlMetadata')
            ?? data_get($payload, 'candidates.0.url_context_metadata.url_metadata');
        $firstUrlMetadata = is_array($urlMetadata) ? ($urlMetadata[0] ?? null) : null;

        if (! is_string($text)) {
            throw new UnexpectedValueException('Gemini URL Context matnli javob qaytarmadi.');
        }

        return new GeminiUrlContextResponse(
            text: $text,
            retrievalStatus: is_array($firstUrlMetadata)
                ? (string) ($firstUrlMetadata['urlRetrievalStatus'] ?? $firstUrlMetadata['url_retrieval_status'] ?? '')
                : '',
            retrievedUrl: is_array($firstUrlMetadata)
                ? $this->nullableString($firstUrlMetadata['retrievedUrl'] ?? $firstUrlMetadata['retrieved_url'] ?? null)
                : null,
        );
    }

    private function request(): PendingRequest
    {
        $apiKey = config('gemini.api_key');
        $timeout = max(1, (int) config('gemini.request_timeout', 45));

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new UnexpectedValueException('Gemini API kaliti sozlanmagan.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->connectTimeout(min(10, $timeout))
            ->timeout($timeout);
    }

    private function endpoint(string $model): string
    {
        $baseUrl = config('gemini.base_url');
        $baseUrl = is_string($baseUrl) && trim($baseUrl) !== ''
            ? trim($baseUrl)
            : 'https://generativelanguage.googleapis.com/v1beta/';
        $model = preg_replace('#^models/#', '', trim($model));

        if (! is_string($model) || $model === '') {
            throw new UnexpectedValueException('Gemini modeli sozlanmagan.');
        }

        return rtrim($baseUrl, '/').'/models/'.rawurlencode($model).':generateContent';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
