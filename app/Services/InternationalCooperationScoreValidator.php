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

        $point = $result->universityTier === null
            ? null
            : InternationalCooperationCriterionRule::pointForUniversityTier(
                $maximumPoint,
                $result->universityTier,
            );

        if ($point === null) {
            return AiEvaluationResult::checking(
                'AI 2.1.6 mezoni uchun ruxsat etilgan universitet Top darajasini qaytarmadi. Inson tekshiruvi zarur.',
            );
        }

        $percentage = InternationalCooperationCriterionRule::percentageForUniversityTier(
            $result->universityTier,
        );

        return new AiEvaluationResult(
            status: 'accepted',
            point: $point,
            reason: trim($result->reason).' Tizim hisob-kitobi: '
                .number_format($maximumPoint, 2, '.', '')
                .' × '.$percentage.'% = '.number_format($point, 2, '.', '').' ball.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
            pageCount: $result->pageCount,
            receivedAmount: $result->receivedAmount,
            universityTier: $result->universityTier,
            publicationTier: $result->publicationTier,
            publicationIssue: $result->publicationIssue,
        );
    }
}
