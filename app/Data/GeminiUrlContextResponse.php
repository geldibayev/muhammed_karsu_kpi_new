<?php

namespace App\Data;

class GeminiUrlContextResponse
{
    public const SUCCESS = 'URL_RETRIEVAL_STATUS_SUCCESS';

    public function __construct(
        public readonly string $text,
        public readonly string $retrievalStatus,
        public readonly ?string $retrievedUrl,
    ) {}

    public function wasRetrieved(): bool
    {
        return $this->retrievalStatus === self::SUCCESS
            && filled($this->retrievedUrl);
    }

    public function matchesRequestedHost(string $requestedUrl): bool
    {
        $requestedHost = parse_url($requestedUrl, PHP_URL_HOST);
        $retrievedHost = parse_url((string) $this->retrievedUrl, PHP_URL_HOST);

        return is_string($requestedHost)
            && is_string($retrievedHost)
            && mb_strtolower(rtrim($requestedHost, '.')) === mb_strtolower(rtrim($retrievedHost, '.'));
    }

    public function failureReason(): string
    {
        return match ($this->retrievalStatus) {
            'URL_RETRIEVAL_STATUS_PAYWALL' => 'URL mazmuni login yoki pulli obuna ortida bo‘lgani sababli avtomatik tekshirib bo‘lmadi.',
            'URL_RETRIEVAL_STATUS_UNSAFE' => 'URL mazmuni Gemini xavfsizlik tekshiruvidan o‘tmadi.',
            'URL_RETRIEVAL_STATUS_ERROR' => 'URL mazmunini Gemini URL Context orqali ochib bo‘lmadi.',
            default => 'Gemini URL mazmuni muvaffaqiyatli yuklanganini tasdiqlamadi.',
        };
    }
}
