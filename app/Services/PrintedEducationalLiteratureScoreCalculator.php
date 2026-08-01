<?php

namespace App\Services;

use App\Data\AiEvaluationResult;

class PrintedEducationalLiteratureScoreCalculator
{
    private const int PagesPerPrintedSheet = 16;

    /** @var array<string, float> */
    private const array PointsPerPrintedSheet = [
        '1.2' => 0.4,
        '1.3' => 0.3,
    ];

    public function calculate(string $criterionCode, int $pageCount, int $authorCount): float
    {
        $rate = self::PointsPerPrintedSheet[$criterionCode] ?? null;

        if ($rate === null || $pageCount < 1 || $authorCount < 1 || $authorCount > 1000) {
            return 0;
        }

        return round(($pageCount / self::PagesPerPrintedSheet) * $rate / $authorCount, 4);
    }

    public function apply(AiEvaluationResult $result, string $criterionCode): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        if (! array_key_exists($criterionCode, self::PointsPerPrintedSheet)) {
            return AiEvaluationResult::checking('Bosma o\'quv adabiyoti uchun ball qoidasi topilmadi.');
        }

        if ($result->pageCount === null || $result->pageCount < 1) {
            return AiEvaluationResult::checking('Kitobdagi jami sahifalar soni ishonchli aniqlanmadi. Inson tekshiruvi zarur.');
        }

        if ($result->authorCount === null || $result->authorCount < 1) {
            return AiEvaluationResult::checking('Kitobdagi jami mualliflar soni ishonchli aniqlanmadi. Inson tekshiruvi zarur.');
        }

        $rate = self::PointsPerPrintedSheet[$criterionCode];
        $point = $this->calculate($criterionCode, $result->pageCount, $result->authorCount);

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason.' Server hisobi: '.$result->pageCount.' sahifa / '
                .self::PagesPerPrintedSheet.' × '.number_format($rate, 1, '.', '')
                .' / '.$result->authorCount.' muallif = '.number_format($point, 4, '.', '').' ball.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
            pageCount: $result->pageCount,
        );
    }
}
