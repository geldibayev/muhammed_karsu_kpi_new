<?php

namespace App\Support;

class FixedPerResourceHumanReviewCriterionRule
{
    /** @var array<string, array<string, float>> */
    private const POINTS = [
        '3.1.12' => [
            'hold_degrees' => 3.0,
            'no_degrees' => 3.0,
            'foreign_lang' => 3.0,
            'physical' => 3.0,
        ],
        '4.1.3' => [
            'hold_degrees' => 0.5,
            'no_degrees' => 0.75,
            'foreign_lang' => 0.25,
            'physical' => 0.75,
        ],
        '4.1.4' => [
            'hold_degrees' => 0.5,
            'no_degrees' => 0.75,
            'foreign_lang' => 0.5,
            'physical' => 1.0,
        ],
        '4.1.5' => [
            'hold_degrees' => 1.0,
            'no_degrees' => 1.0,
            'foreign_lang' => 1.0,
            'physical' => 1.0,
        ],
    ];

    /** @return array<int, string> */
    public static function criterionCodes(): array
    {
        return array_keys(self::POINTS);
    }

    public static function supports(?string $criterionCode): bool
    {
        return $criterionCode !== null && array_key_exists($criterionCode, self::POINTS);
    }

    public static function pointFor(string $criterionCode, string $evaluationCategory): ?float
    {
        return self::POINTS[$criterionCode][$evaluationCategory] ?? null;
    }
}
