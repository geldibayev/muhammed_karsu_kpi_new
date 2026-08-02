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
                || (Gate::forUser($user)->allows('view-ai-status') && $datum->usesAiChecking())
                || $this->isRatingSubmissionVisible($user, $datum));
    }

    public function download(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum)
                || $this->isAssignedReviewer($user, $datum)
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

    public function requeueAiEvaluation(User $user, Datum $datum): bool
    {
        if (! $user->isSuperAdmin()
            || (string) $user->hemis_id !== (string) config('kpi.settings_manager_hemis_id')
            || $datum->status !== DatumStatus::Cancelled->value
            || ! $datum->usesAiChecking()) {
            return false;
        }

        $lastAiEvaluationId = (int) $datum->histories()
            ->where('message_type', 'ai_evaluation')
            ->max('id');
        $lastAiQueueId = (int) $datum->histories()
            ->whereIn('message_type', ['submission_created', 'ai_queued'])
            ->max('id');
        $lastHumanDecisionId = (int) $datum->histories()
            ->whereIn('message_type', [
                'manual_review_approved',
                'manual_review_rejected',
                'h_index_review_approved',
                'criterion_transferred',
            ])
            ->max('id');

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
}
