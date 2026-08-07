<?php

namespace App\Support;

class ForeignLanguageCertificateCriterionRule
{
    public const CODE = '2.1.3';

    /** @var array<string, string> */
    public const LEVEL_LABELS = [
        'a1' => 'A1',
        'a2' => 'A2',
        'b1' => 'B1',
        'b2' => 'B2',
        'c1' => 'C1',
        'c2' => 'C2',
    ];

    /** @var array<string, float> */
    private const SPECIAL_DEPARTMENT_POINTS = [
        'a1' => 0,
        'a2' => 0,
        'b1' => 0,
        'b2' => 0,
        'c1' => 3,
        'c2' => 6,
    ];

    /** @var array<string, float> */
    private const DEGREE_HOLDER_POINTS = [
        'a1' => 0,
        'a2' => 0,
        'b1' => 2,
        'b2' => 5,
        'c1' => 7,
        'c2' => 10,
    ];

    /** @var array<string, float> */
    private const OTHER_POINTS = [
        'a1' => 0,
        'a2' => 0,
        'b1' => 3,
        'b2' => 6,
        'c1' => 8,
        'c2' => 10,
    ];

    public const DESCRIPTION_UZ = 'Faqat 1 ta xorijiy til sertifikati fayli yuklanadi. Inson tekshiruvchi faqat sertifikat darajasini tasdiqlaydi, ball serverda avtomatik hisoblanadi. Chet tillari fakultetining Rus tili va adabiyoti kafedrasidan tashqari kafedralarida: A1, A2, B1 va B2 — 0 ball, C1 — 3 ball, C2 — 6 ball. Boshqa kafedralarda: A1 va A2 — 0 ball; B1 — ilmiy darajali uchun 2, boshqalarga 3 ball; B2 — ilmiy darajali uchun 5, boshqalarga 6 ball; C1 — ilmiy darajali uchun 7, boshqalarga 8 ball; C2 — barchaga 10 ball.';

    public static function pointFor(
        string $level,
        string $evaluationCategory,
        ?int $departmentId,
        ?int $facultyId,
    ): ?float {
        $level = mb_strtolower(trim($level));

        if (! array_key_exists($level, self::LEVEL_LABELS)) {
            return null;
        }

        if (self::isSpecialForeignLanguageDepartment($departmentId, $facultyId)) {
            return self::SPECIAL_DEPARTMENT_POINTS[$level];
        }

        return $evaluationCategory === 'hold_degrees'
            ? self::DEGREE_HOLDER_POINTS[$level]
            : self::OTHER_POINTS[$level];
    }

    public static function isSpecialForeignLanguageDepartment(
        ?int $departmentId,
        ?int $facultyId,
    ): bool {
        return $facultyId === (int) config('kpi.foreign_language_faculty_department_id')
            && $departmentId !== (int) config('kpi.russian_language_department_id');
    }

    public static function levelFromHistory(string $message): ?string
    {
        $normalizedMessage = mb_strtolower($message);

        foreach (array_reverse(array_keys(self::LEVEL_LABELS)) as $level) {
            if (preg_match('/\b'.preg_quote($level, '/').'\b/u', $normalizedMessage) === 1) {
                return $level;
            }
        }

        return null;
    }

    public static function levelFromLegacyPoint(float $point): ?string
    {
        foreach ([
            'a1' => 0.5,
            'b1' => 0.75,
            'b2' => 1.0,
            'c1' => 1.5,
            'c2' => 2.0,
        ] as $level => $legacyPoint) {
            if (abs($point - $legacyPoint) < 0.00005) {
                return $level;
            }
        }

        return null;
    }
}
