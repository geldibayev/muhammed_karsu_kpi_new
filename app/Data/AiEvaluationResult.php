<?php

namespace App\Data;

use DateTimeImmutable;
use JsonException;
use UnexpectedValueException;

class AiEvaluationResult
{
    public function __construct(
        public readonly string $status,
        public readonly float $point,
        public readonly string $reason,
        public readonly ?int $authorCount = null,
        public readonly ?string $resourceDate = null,
        public readonly ?int $pageCount = null,
        public readonly ?float $receivedAmount = null,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(
        array $payload,
        float $maximumPoint,
        bool $requiresTranslationEvidence = false,
    ): self {
        $payloadKeys = array_keys($payload);
        sort($payloadKeys);

        $allowedPayloadKeys = [
            ['point', 'reason', 'status'],
            ['author_count', 'point', 'reason', 'status'],
            ['point', 'reason', 'resource_date', 'status'],
            ['author_count', 'point', 'reason', 'resource_date', 'status'],
            ['author_count', 'page_count', 'point', 'reason', 'resource_date', 'status'],
            ['author_count', 'reason', 'received_amount', 'resource_date', 'status'],
        ];

        if ($requiresTranslationEvidence) {
            $allowedPayloadKeys = [[
                'author_count',
                'is_translation',
                'point',
                'reason',
                'resource_date',
                'source_language',
                'status',
                'target_language',
            ]];
        }

        if (! in_array($payloadKeys, $allowedPayloadKeys, true)) {
            throw new UnexpectedValueException('AI javobida kutilmagan yoki yetishmayotgan maydon bor.');
        }

        if (! is_finite($maximumPoint) || $maximumPoint < 0) {
            throw new UnexpectedValueException('Mezon ball chegarasi noto\'g\'ri.');
        }

        $status = $payload['status'] ?? null;
        $point = $payload['point'] ?? null;
        $reason = $payload['reason'] ?? null;
        $authorCount = $payload['author_count'] ?? null;
        $resourceDate = $payload['resource_date'] ?? null;
        $pageCount = $payload['page_count'] ?? null;
        $receivedAmount = $payload['received_amount'] ?? null;
        $usesReceivedAmount = array_key_exists('received_amount', $payload);
        $isTranslation = $payload['is_translation'] ?? null;
        $sourceLanguage = $payload['source_language'] ?? null;
        $targetLanguage = $payload['target_language'] ?? null;

        if (! is_string($status) || ! in_array($status, ['accepted', 'cancelled', 'checking'], true)) {
            throw new UnexpectedValueException('AI statusi ruxsat etilgan qiymatlardan biri emas.');
        }

        if (! $usesReceivedAmount
            && ((! is_int($point) && ! is_float($point)) || ! is_finite((float) $point) || $point < 0)) {
            throw new UnexpectedValueException('AI balli manfiy bo\'lmagan son bo\'lishi kerak.');
        }

        if (! is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 5000) {
            throw new UnexpectedValueException('AI xulosasi bo\'sh yoki juda uzun.');
        }

        if (! $usesReceivedAmount && $point > $maximumPoint) {
            throw new UnexpectedValueException('AI balli mezon chegarasidan oshib ketdi.');
        }

        if ($usesReceivedAmount
            && ((! is_int($receivedAmount) && ! is_float($receivedAmount))
                || ! is_finite((float) $receivedAmount)
                || $receivedAmount < 0
                || $receivedAmount > 9_999_999_999_999_999.99)) {
            throw new UnexpectedValueException('Universitet hisobiga tushgan mablag‘ noto‘g‘ri formatda.');
        }

        if ($usesReceivedAmount && $status === 'accepted' && $receivedAmount <= 0) {
            throw new UnexpectedValueException('Qabul qilingan resurs uchun tushgan mablag‘ musbat bo‘lishi kerak.');
        }

        if ($authorCount !== null
            && (! is_int($authorCount) || $authorCount < 0 || $authorCount > 1000)) {
            throw new UnexpectedValueException('AI mualliflar soni 0 dan 1000 gacha butun son bo‘lishi kerak.');
        }

        if ($status === 'accepted' && $authorCount !== null && $authorCount < 1) {
            throw new UnexpectedValueException('Qabul qilingan resurs uchun mualliflar soni kamida 1 bo‘lishi kerak.');
        }

        if ($pageCount !== null
            && (! is_int($pageCount) || $pageCount < 0 || $pageCount > 100000)) {
            throw new UnexpectedValueException('AI sahifalar soni 0 dan 100000 gacha butun son bo\'lishi kerak.');
        }

        if ($status === 'accepted' && $pageCount !== null && $pageCount < 1) {
            throw new UnexpectedValueException('Qabul qilingan resurs uchun sahifalar soni kamida 1 bo\'lishi kerak.');
        }

        if ($resourceDate !== null && ! is_string($resourceDate)) {
            throw new UnexpectedValueException('AI resurs sanasi matn ko‘rinishida bo‘lishi kerak.');
        }

        if ($requiresTranslationEvidence) {
            if (! is_bool($isTranslation)
                || ! is_string($sourceLanguage)
                || ! is_string($targetLanguage)
                || mb_strlen($sourceLanguage) > 100
                || mb_strlen($targetLanguage) > 100) {
                throw new UnexpectedValueException('AI tarjima holati yoki tillarni noto‘g‘ri formatda qaytardi.');
            }

            $sourceLanguage = trim($sourceLanguage);
            $targetLanguage = trim($targetLanguage);

            if ($status === 'accepted'
                && (! $isTranslation
                    || $sourceLanguage === ''
                    || $targetLanguage === ''
                    || mb_strtolower($sourceLanguage) === mb_strtolower($targetLanguage))) {
                throw new UnexpectedValueException('1.4 mezoni uchun tarjima va o‘zaro farqli tillar tasdiqlanmadi.');
            }
        }

        $resourceDate = is_string($resourceDate) && trim($resourceDate) !== ''
            ? trim($resourceDate)
            : null;

        if ($resourceDate !== null && preg_match('/^\d{4}$/', $resourceDate) !== 1) {
            $parsedResourceDate = DateTimeImmutable::createFromFormat('!Y-m-d', $resourceDate);

            if ($parsedResourceDate === false || $parsedResourceDate->format('Y-m-d') !== $resourceDate) {
                throw new UnexpectedValueException('AI resurs sanasi YYYY-MM-DD yoki YYYY formatiga mos emas.');
            }
        }

        return new self(
            status: $status,
            point: $status === 'accepted' && ! $usesReceivedAmount ? (float) $point : 0,
            reason: trim($reason),
            authorCount: $status === 'accepted' ? $authorCount : null,
            resourceDate: $resourceDate,
            pageCount: $status === 'accepted' ? $pageCount : null,
            receivedAmount: $status === 'accepted' && $usesReceivedAmount ? (float) $receivedAmount : null,
        );
    }

    /** @throws JsonException */
    public static function fromJson(
        string $json,
        float $maximumPoint,
        bool $requiresTranslationEvidence = false,
    ): self {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || array_is_list($payload)) {
            throw new UnexpectedValueException('AI javobi JSON obyekt emas.');
        }

        return self::fromPayload($payload, $maximumPoint, $requiresTranslationEvidence);
    }

    public static function checking(string $reason): self
    {
        return new self('checking', 0, $reason);
    }
}
