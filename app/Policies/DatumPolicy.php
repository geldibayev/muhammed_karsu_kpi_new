<?php

namespace App\Policies;

use App\Enums\DatumStatus;
use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\User;
use App\Support\EducationalContentCriterionRule;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\ResourceUploadWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DatumPolicy
{
    public function __construct(private ResourceUploadWindow $resourceUploadWindow) {}

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('teacher')
            || $user->hasRole('user');
    }

    public function view(User $user, Datum $datum): bool
    {
        if ($datum->status === DatumStatus::Deleted->value) {
            return $this->ownsDatumOrIsSuperAdmin($user, $datum);
        }

        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum)
                || $this->isAssignedReviewer($user, $datum)
                || $this->overrideAcceptance($user, $datum)
                || $this->overrideCancellation($user, $datum)
                || (Gate::forUser($user)->allows('view-ai-status') && $datum->usesAiChecking())
                || $this->isRatingSubmissionVisible($user, $datum));
    }

    public function download(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum)
                || $this->isAssignedReviewer($user, $datum)
                || $this->overrideAcceptance($user, $datum)
                || $this->overrideCancellation($user, $datum)
                || $this->review($user, $datum)
                || $this->correctAcceptedScore($user, $datum)
                || $this->isFinalizedRatingSubmissionVisible($user, $datum));
    }

    public function delete(User $user, Datum $datum): bool
    {
        return $this->resourceUploadWindow->isOpen()
            && $datum->system_key === null
            && $datum->status !== 'deleted'
            && $this->ownsDatumOrIsSuperAdmin($user, $datum);
    }

    public function review(User $user, Datum $datum): bool
    {
        return in_array($datum->status, ['received', 'checking'], true)
            && ($user->isSuperAdmin()
                || $this->isAssignedReviewer($user, $datum)
                || ($datum->usesAiChecking() && $user->can('manage-ai-operations')));
    }

    public function updateAcceptedScore(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Accepted->value
            && $datum->loadMissing('criterion:id,code')->criterion?->code !== Criterion::H_INDEX_CODE
            && ($user->isSuperAdmin() || $this->canManageAssignedFinalDecision($user, $datum));
    }

    public function updateHIndexProfile(User $user, Datum $datum): bool
    {
        return $user->isSuperAdmin()
            && $datum->status === DatumStatus::Accepted->value
            && $datum->loadMissing('criterion:id,code')->criterion?->code === Criterion::H_INDEX_CODE;
    }

    public function correctAcceptedScore(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Accepted->value
            && $datum->usesAiChecking()
            && ! $user->isSuperAdmin()
            && $user->can('manage-ai-operations');
    }

    public function overrideAcceptance(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Accepted->value
            && $this->canOverrideFinalDecision($user, $datum);
    }

    public function overrideCancellation(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Cancelled->value
            && $this->canOverrideFinalDecision($user, $datum);
    }

    public function confirmFinalReview(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Accepted->value
            && ! $datum->isFinalReviewConfirmed()
            && ($user->isSuperAdmin()
                || $this->isAssignedReviewer($user, $datum)
                || $this->isCriterionReviewer($user, $datum));
    }

    public function overrideAiAcceptance(User $user, Datum $datum): bool
    {
        return $this->overrideAcceptance($user, $datum);
    }

    public function overrideAiCancellation(User $user, Datum $datum): bool
    {
        return $this->overrideCancellation($user, $datum);
    }

    public function changeEducationalContentType(User $user, Datum $datum): bool
    {
        return $datum->status === DatumStatus::Accepted->value
            && $datum->criterion()->where('code', EducationalContentCriterionRule::CODE)->exists()
            && ($this->canOverrideFinalDecision($user, $datum) || $this->isAssignedReviewer($user, $datum));
    }

    public function requeueAiEvaluation(User $user, Datum $datum): bool
    {
        if (! $user->isSuperAdmin()
            || $datum->status !== DatumStatus::Cancelled->value
            || ! $datum->usesAiChecking()) {
            return false;
        }

        $lastAiEvaluationId = $this->latestHistoryId($datum, ['ai_evaluation']);
        $lastAiQueueId = $this->latestHistoryId($datum, ['submission_created', 'ai_queued']);
        $lastHumanDecisionId = $this->latestHistoryId($datum, [
            'manual_review_approved',
            'manual_review_rejected',
            'h_index_review_approved',
            'human_override_ai_rejected',
            'human_override_ai_approved',
            'human_override_rejected',
            'human_override_approved',
            'criterion_transferred',
        ]);

        return $lastAiEvaluationId > $lastAiQueueId
            && $lastAiEvaluationId > $lastHumanDecisionId;
    }

    public function replaceFourOneOneReference(User $user, Datum $datum): bool
    {
        if (! $user->isActive()
            || $user->isUploadBlocked()
            || (! $user->hasRole('teacher') && ! $user->hasRole('user'))
            || $datum->user_id !== $user->id
            || $datum->status !== DatumStatus::Cancelled->value
            || $datum->histories()->where('message_type', 'four_one_one_reference_replacement_submitted')->exists()) {
            return false;
        }

        $criterion = $datum->criterion()->first(['id', 'code', 'checking', 'upload', 'status', 'report_id']);

        if ($criterion?->code !== FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE
            || $criterion->checking !== 'ai'
            || $criterion->upload !== '1'
            || $criterion->status !== '1'
            || ! $criterion->report()->where('status', '1')->exists()
            || ! $criterion->criterionEvaluations()
                ->where('evaluation', $user->degree)
                ->where('has', '1')
                ->exists()) {
            return false;
        }

        $recheckId = $this->latestHistoryId($datum, ['ai_four_one_one_reference_recheck_queued']);
        $aiEvaluation = $datum->histories()
            ->where('message_type', 'ai_evaluation')
            ->latest('id')
            ->first(['id', 'type', 'message']);
        $lastHumanDecisionId = $this->latestHistoryId($datum, [
            'manual_review_approved',
            'manual_review_rejected',
            'h_index_review_approved',
            'human_override_ai_rejected',
            'human_override_ai_approved',
            'human_override_rejected',
            'human_override_approved',
            'criterion_transferred',
        ]);

        return $recheckId > 0
            && $aiEvaluation !== null
            && $aiEvaluation->type === 'error'
            && $aiEvaluation->id > $recheckId
            && $aiEvaluation->id > $lastHumanDecisionId
            && $this->isReferenceRejection((string) $aiEvaluation->message);
    }

    public function transferCriterion(User $user, Datum $datum): bool
    {
        return $this->review($user, $datum)
            && ! CriterionManualScoreOption::query()
                ->where('criterion_id', $datum->criterion_id)
                ->where('code', CriterionManualScoreOption::FIXED_APPROVAL_CODE)
                ->where('active', true)
                ->exists();
    }

    private function ownsDatumOrIsSuperAdmin(User $user, Datum $datum): bool
    {
        return $user->isSuperAdmin() || $datum->user_id === $user->id;
    }

    private function canOverrideFinalDecision(User $user, Datum $datum): bool
    {
        if ($user->isSuperAdmin()
            || $this->isAssignedReviewer($user, $datum)
            || $this->isCriterionReviewer($user, $datum)) {
            return true;
        }

        return ! $this->isAssignedFinalDecisionReviewer($user)
            && (string) config('kpi.accepted_ai_reviewer_hemis_id') === (string) $user->hemis_id;
    }

    private function canManageAssignedFinalDecision(User $user, Datum $datum): bool
    {
        return $this->isAssignedFinalDecisionReviewer($user)
            && $this->isCriterionReviewer($user, $datum);
    }

    private function isAssignedFinalDecisionReviewer(User $user): bool
    {
        return (string) config('kpi.assigned_final_decision_reviewer_hemis_id') === (string) $user->hemis_id;
    }

    private function isCriterionReviewer(User $user, Datum $datum): bool
    {
        $criterion = $datum->criterion()->first(['id', 'code', 'checking']);

        if ($criterion?->checking === 'ai') {
            $reviewerHemisId = AiHumanReviewAssignment::reviewerHemisIdFor($criterion);

            return $reviewerHemisId !== null
                && (string) $reviewerHemisId === (string) $user->hemis_id;
        }

        return CriterionReviewerAssignment::query()
            ->where('hemis_id', $user->hemis_id)
            ->where('criterion_id', $datum->criterion_id)
            ->exists();
    }

    private function isRatingSubmissionVisible(User $user, Datum $datum): bool
    {
        if ($this->isFinalizedRatingSubmissionVisible($user, $datum)) {
            return true;
        }

        return $user->can('view-ratings') && in_array($datum->status, [
            DatumStatus::Received->value,
            DatumStatus::Checking->value,
        ], true) && $datum->criterion()
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->exists();
    }

    private function isFinalizedRatingSubmissionVisible(User $user, Datum $datum): bool
    {
        return $user->can('view-ratings') && in_array($datum->status, [
            DatumStatus::Accepted->value,
            DatumStatus::Cancelled->value,
        ], true);
    }

    private function isAssignedReviewer(User $user, Datum $datum): bool
    {
        if ($datum->usesAiChecking()) {
            return $datum->reviewer_hemis_id !== null
                && (string) $datum->reviewer_hemis_id === (string) $user->hemis_id;
        }

        return CriterionReviewerAssignment::query()
            ->where('hemis_id', $user->hemis_id)
            ->where('criterion_id', $datum->criterion_id)
            ->exists();
    }

    /** @param  array<int, string>  $messageTypes */
    private function latestHistoryId(Datum $datum, array $messageTypes): int
    {
        if ($datum->relationLoaded('histories')) {
            return (int) $datum->histories
                ->whereIn('message_type', $messageTypes)
                ->max('id');
        }

        return (int) $datum->histories()
            ->whereIn('message_type', $messageTypes)
            ->max('id');
    }

    private function isReferenceRejection(string $reason): bool
    {
        $reason = Str::lower($reason);

        if (Str::contains($reason, [
            "ma'lumotnoma emas",
            'ma’lumotnoma emas',
            'маълумотнома эмас',
            'maǵlıwmatnama emes',
            "mag'liwmatnama emes",
            'мағлыўматнама емес',
            'не является справк',
            'справкой не является',
        ])) {
            return false;
        }

        return Str::contains($reason, [
            "ma'lumotnoma",
            'ma’lumotnoma',
            'маълумотнома',
            'maǵlıwmatnama',
            "mag'liwmatnama",
            'мағлыўматнама',
            'справк',
        ]);
    }
}
