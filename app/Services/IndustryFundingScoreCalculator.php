<?php

namespace App\Services;

use App\Data\AiEvaluationResult;

class IndustryFundingScoreCalculator
{
    public function calculate(float $receivedAmount, int $authorCount): float
    {
        if (! is_finite($receivedAmount) || $receivedAmount <= 0) {
            throw new \InvalidArgumentException('Tushgan mablag‘ musbat son bo‘lishi kerak.');
        }

        if ($authorCount < 1 || $authorCount > 1000) {
            throw new \InvalidArgumentException('Hammualliflar soni 1 dan 1000 gacha bo‘lishi kerak.');
        }

        return round(($receivedAmount / 1_000_000) / $authorCount, 4);
    }

    public function apply(AiEvaluationResult $result): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        if ($result->receivedAmount === null || $result->receivedAmount <= 0) {
            return AiEvaluationResult::checking(
                'Universitet hisobiga tushgan mablag‘ miqdori ishonchli aniqlanmadi.',
            );
        }

        if ($result->authorCount === null || $result->authorCount < 1) {
            return AiEvaluationResult::checking('Hammualliflar soni ishonchli aniqlanmadi.');
        }

        $point = $this->calculate($result->receivedAmount, $result->authorCount);

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason.' Server hisobi: '
                .number_format($result->receivedAmount, 2, '.', '')
                .' so‘m / 1 000 000 / '.$result->authorCount.' hammuallif = '
                .number_format($point, 4, '.', '').' ball.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
            receivedAmount: $result->receivedAmount,
        );
    }
}
