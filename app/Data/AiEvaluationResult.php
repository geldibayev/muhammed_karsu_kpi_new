<?php

namespace App\Data;

use JsonException;
use UnexpectedValueException;

class AiEvaluationResult
{
    public function __construct(
        public readonly string $status,
        public readonly float $point,
        public readonly string $reason,
        public readonly ?int $authorCount = null,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload, float $maximumPoint): self
    {
        $payloadKeys = array_keys($payload);
        sort($payloadKeys);

        if (! in_array($payloadKeys, [
            ['point', 'reason', 'status'],
            ['author_count', 'point', 'reason', 'status'],
        ], true)) {
            throw new UnexpectedValueException('AI javobida kutilmagan yoki yetishmayotgan maydon bor.');
        }

        if (! is_finite($maximumPoint) || $maximumPoint < 0) {
            throw new UnexpectedValueException('Mezon ball chegarasi noto\'g\'ri.');
        }

        $status = $payload['status'] ?? null;
        $point = $payload['point'] ?? null;
        $reason = $payload['reason'] ?? null;
        $authorCount = $payload['author_count'] ?? null;

        if (! is_string($status) || ! in_array($status, ['accepted', 'cancelled', 'checking'], true)) {
            throw new UnexpectedValueException('AI statusi ruxsat etilgan qiymatlardan biri emas.');
        }

        if ((! is_int($point) && ! is_float($point)) || ! is_finite((float) $point) || $point < 0) {
            throw new UnexpectedValueException('AI balli manfiy bo\'lmagan son bo\'lishi kerak.');
        }

        if (! is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 5000) {
            throw new UnexpectedValueException('AI xulosasi bo\'sh yoki juda uzun.');
        }

        if ($point > $maximumPoint) {
            throw new UnexpectedValueException('AI balli mezon chegarasidan oshib ketdi.');
        }

        if ($authorCount !== null
            && (! is_int($authorCount) || $authorCount < 0 || $authorCount > 1000)) {
            throw new UnexpectedValueException('AI mualliflar soni 0 dan 1000 gacha butun son bo‘lishi kerak.');
        }

        if ($status === 'accepted' && $authorCount !== null && $authorCount < 1) {
            throw new UnexpectedValueException('Qabul qilingan resurs uchun mualliflar soni kamida 1 bo‘lishi kerak.');
        }

        return new self(
            status: $status,
            point: $status === 'accepted' ? (float) $point : 0,
            reason: trim($reason),
            authorCount: $status === 'accepted' ? $authorCount : null,
        );
    }

    /** @throws JsonException */
    public static function fromJson(string $json, float $maximumPoint): self
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || array_is_list($payload)) {
            throw new UnexpectedValueException('AI javobi JSON obyekt emas.');
        }

        return self::fromPayload($payload, $maximumPoint);
    }

    public static function checking(string $reason): self
    {
        return new self('checking', 0, $reason);
    }
}
