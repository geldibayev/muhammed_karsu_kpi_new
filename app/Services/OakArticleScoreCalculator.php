<?php

namespace App\Services;

use App\Data\AiEvaluationResult;
use App\Support\OakArticleCriterionRule;
use InvalidArgumentException;

class OakArticleScoreCalculator
{
    public function basePoint(string $evaluationCategory): float
    {
        return $evaluationCategory === 'hold_degrees'
            ? OakArticleCriterionRule::WITH_DEGREE_BASE_POINT
            : OakArticleCriterionRule::WITHOUT_DEGREE_BASE_POINT;
    }

    public function calculate(string $evaluationCategory, int $authorCount): float
    {
        if ($authorCount < 1 || $authorCount > 1000) {
            throw new InvalidArgumentException('Mualliflar soni 1 dan 1000 gacha bo‘lishi kerak.');
        }

        return round($this->basePoint($evaluationCategory) / $authorCount, 4);
    }

    public function apply(AiEvaluationResult $result, string $evaluationCategory): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        if ($result->authorCount === null || $result->authorCount < 1) {
            return AiEvaluationResult::checking(
                'AI mualliflar sonini ishonchli aniqlamadi. Inson tekshiruvi zarur.',
            );
        }

        $basePoint = $this->basePoint($evaluationCategory);
        $point = $this->calculate($evaluationCategory, $result->authorCount);

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: $result->reason.' Bazaviy ball: '.number_format($basePoint, 2, '.', '')
                .'. Mualliflar soni: '.$result->authorCount
                .'. Taqsimlangan ball: '.number_format($point, 4, '.', '').'.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
        );
    }
}
