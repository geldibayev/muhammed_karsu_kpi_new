<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Formula;

class ResolveAiManualPointMaximum
{
    public function handle(Datum $datum): ?float
    {
        $datum->loadMissing([
            'criterion.criterionEvaluations',
            'criterion.formula',
            'user',
        ]);
        $criterion = $datum->criterion;

        if ($criterion === null || $criterion->checking !== 'ai') {
            return null;
        }

        if ($criterion->ai_submission_max_point !== null
            || $criterion->usesFormula(Formula::Unlimited)) {
            return $criterion->aiSubmissionMaximum();
        }

        $evaluation = $criterion->criterionEvaluations
            ->firstWhere('evaluation', $datum->user?->degree);

        if (! $evaluation instanceof CriterionEvaluation || $evaluation->has !== '1') {
            return null;
        }

        return $criterion->aiSubmissionMaximum((float) $evaluation->score);
    }
}
