<?php

namespace App\Services;

use App\Data\AiEvaluationResult;

class AiAuthorPointDistributor
{
    public function handle(
        AiEvaluationResult $result,
        string $criterionPrompt,
        ?bool $divideByAuthors = null,
    ): AiEvaluationResult {
        $requiresAuthorCount = str_contains($criterionPrompt, 'author_count');

        if (! $requiresAuthorCount || $result->status !== 'accepted' || $divideByAuthors === false) {
            return $result;
        }

        if ($result->authorCount === null || $result->authorCount < 1) {
            return AiEvaluationResult::checking(
                'AI mualliflar sonini ishonchli aniqlamadi. Inson tekshiruvi zarur.',
            );
        }

        if ($this->pointIsAlreadyDistributed($criterionPrompt)) {
            return $result;
        }

        $distributedPoint = round($result->point / $result->authorCount, 4);

        return new AiEvaluationResult(
            status: $result->status,
            point: $distributedPoint,
            reason: $result->reason.' Mualliflar soni: '.$result->authorCount
                .'. Taqsimlangan ball: '.number_format($distributedPoint, 4, '.', '').'.',
            authorCount: $result->authorCount,
            resourceDate: $result->resourceDate,
        );
    }

    private function pointIsAlreadyDistributed(string $criterionPrompt): bool
    {
        $normalizedPrompt = mb_strtolower($criterionPrompt);

        return str_contains($normalizedPrompt, "mualliflar soniga bo'lib")
            || str_contains($normalizedPrompt, 'mualliflar soniga bo‘lib')
            || preg_match('/\/\s*mualliflar[_\s]?soni/u', $normalizedPrompt) === 1;
    }
}
