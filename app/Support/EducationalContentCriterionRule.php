<?php

namespace App\Support;

class EducationalContentCriterionRule
{
    public const CODE = '1.1';

    /** @var array<string, int> */
    public const PERCENTAGES = [
        'video_lesson' => 50,
        'video_clip' => 40,
        'presentation' => 10,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'video_lesson' => 'Videodars',
        'video_clip' => 'Videorolik',
        'presentation' => 'Taqdimot',
    ];

    public static function percentageFor(string $resourceType): ?int
    {
        return self::PERCENTAGES[$resourceType] ?? null;
    }

    public static function pointFor(float $maximumPoint, string $resourceType): ?float
    {
        $percentage = self::percentageFor($resourceType);

        return $percentage === null
            ? null
            : round(max(0, $maximumPoint) * $percentage / 100, 4);
    }

    public static function labelFor(string $resourceType): ?string
    {
        return self::LABELS[$resourceType] ?? null;
    }

    public static function resourceTypeFromLegacyPoint(float $point): ?string
    {
        foreach ([
            'video_lesson' => 1.5,
            'video_clip' => 1.0,
            'presentation' => 0.5,
        ] as $resourceType => $legacyPoint) {
            if (abs($point - $legacyPoint) < 0.00005) {
                return $resourceType;
            }
        }

        return null;
    }

    public static function resourceTypeFromHistory(string $message): ?string
    {
        $normalizedMessage = mb_strtolower($message);

        foreach (self::LABELS as $resourceType => $label) {
            if (str_contains($normalizedMessage, mb_strtolower($label))) {
                return $resourceType;
            }
        }

        return null;
    }
}
