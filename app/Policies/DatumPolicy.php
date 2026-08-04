<?php

namespace App\Policies;

use App\Enums\DatumStatus;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DatumPolicy
{
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
                || $this->overrideAiAcceptance($user, $datum)
                || $this->overrideAiCancellation($user, $datum)
                || (Gate::forUser($user)->allows('view-ai-status') && $datum->usesAiChecking())
                || $this->isRatingSubmissionVisible($user, $datum));
    }

    public function download(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum)
                || $this->isAssignedReviewer($user, $datum)
                || $this->overrideAiAcceptance($user, $datum)
                || $this->overrideAiCancellation($user, $datum)
                || $this->isRatingSubmissionVisible($user, $datum));
    }

    public function delete(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted' && $this->ownsDatumOrIsSuperAdmin($user, $datum);
    }

    public function review(User $user, Datum $datum): bool
    {
        return in_array($datum->status, ['received', 'checking'], true)
            && $this->isAssignedReviewer($user, $datum);
    }

    public function overrideAiAcceptance(User $user, Datum $datum): bool
    {
        if ($datum->status !== DatumStatus::Accepted->value
            || ! $datum->usesAiChecking()
            || ! $datum->histories()
                ->where('message_type', 'ai_evaluation')
                ->where('type', 'success')
                ->exists()) {
            return false;
        }

        return $this->canOverrideAiDecision($user);
    }

    public function overrideAiCancellation(User $user, Datum $datum): bool
    {
        if ($datum->status !== DatumStatus::Cancelled->value
            || ! $datum->usesAiChecking()) {
            return false;
        }

        $lastAiRejectionId = (int) $datum->histories()
            ->where('message_type', 'ai_evaluation')
            ->where('type', 'error')
            ->max('id');
        $lastHumanDecisionId = $this->latestHistoryId($datum, [
            'manual_review_approved',
            'manual_review_rejected',
            'human_override_ai_rejected',
            'human_override_ai_approved',
            'criterion_transferred',
        ]);

        return $lastAiRejectionId > $lastHumanDecisionId
            && $this->canOverrideAiDecision($user);
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
            'criterion_transferred',
        ]);

        return $lastAiEvaluationId > $lastAiQueueId
            && $lastAiEvaluationId > $lastHumanDecisionId;
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

    private function canOverrideAiDecision(User $user): bool
    {
        return $user->isSuperAdmin()
            || (string) config('kpi.accepted_ai_reviewer_hemis_id') === (string) $user->hemis_id;
    }

    private function isRatingSubmissionVisible(User $user, Datum $datum): bool
    {
        return in_array($datum->status, [
            DatumStatus::Accepted->value,
            DatumStatus::Cancelled->value,
        ], true) && $user->can('view-ratings');
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
}
