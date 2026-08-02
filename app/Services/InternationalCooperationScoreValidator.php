<?php

namespace App\Services;

use App\Data\AiEvaluationResult;
use App\Support\InternationalCooperationCriterionRule;

class InternationalCooperationScoreValidator
{
    public function handle(AiEvaluationResult $result, float $maximumPoint): AiEvaluationResult
    {
        if ($result->status !== 'accepted') {
            return $result;
        }

        $allowedPoints = InternationalCooperationCriterionRule::allowedPoints($maximumPoint);

        if ($allowedPoints === [] || ! in_array($result->point, $allowedPoints, true)) {
            return AiEvaluationResult::checking(
                'AI 2.1.6 mezoni uchun ruxsat etilmagan ball qaytardi. Inson tekshiruvi zarur.',
            );
        }

        return $result;
    }
}
