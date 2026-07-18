<?php

namespace App\Policies;

use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\User;

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
        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum) || $this->isAssignedReviewer($user, $datum));
    }

    public function download(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted'
            && ($this->ownsDatumOrIsSuperAdmin($user, $datum) || $this->isAssignedReviewer($user, $datum));
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

    private function ownsDatumOrIsSuperAdmin(User $user, Datum $datum): bool
    {
        return $user->isSuperAdmin() || $datum->user_id === $user->id;
    }

    private function isAssignedReviewer(User $user, Datum $datum): bool
    {
        return CriterionReviewerAssignment::query()
            ->where('hemis_id', $user->hemis_id)
            ->where('criterion_id', $datum->criterion_id)
            ->exists();
    }
}
