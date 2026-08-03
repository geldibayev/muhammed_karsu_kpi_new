<?php

namespace App\Services;

use InvalidArgumentException;

class ScientificPublicationHumanReviewScoreCalculator
{
    public const PUBLICATION_TIER_POINTS = [
        'q1' => 5.0,
        'q2' => 5.0,
        'q3' => 4.0,
        'q4' => 4.0,
        'conference' => 2.5,
    ];

    public function impactFactorPoint(float $maximumPoint, int $impactFactor): float
    {
        if ($impactFactor < 1 || $impactFactor > 1000) {
            throw new InvalidArgumentException('Impakt faktor 1 dan 1000 gacha bo‘lishi kerak.');
        }

        return round(max(0, $maximumPoint) * min($impactFactor, 10) / 10, 4);
    }

    public function publicationTierPoint(string $publicationTier): float
    {
        return self::PUBLICATION_TIER_POINTS[$publicationTier]
            ?? throw new InvalidArgumentException('Jurnal kvartili yoki nashr turi noto‘g‘ri.');
    }

    public function authorDividedPoint(float $basePoint, int $authorCount): float
    {
        if ($authorCount < 1 || $authorCount > 1000) {
            throw new InvalidArgumentException('Mualliflar soni 1 dan 1000 gacha bo‘lishi kerak.');
        }

        return round(max(0, $basePoint) / $authorCount, 4);
    }
}
