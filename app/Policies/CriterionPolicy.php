<?php

namespace App\Policies;

use App\Models\Criterion;
use App\Models\CriterionUploadPermission;
use App\Models\Option;
use App\Models\User;
use App\Support\ResourceUploadWindow;

class CriterionPolicy
{
    public function __construct(private ResourceUploadWindow $resourceUploadWindow) {}

    public function submit(User $user, Criterion $criterion): bool
    {
        $hasTeacherRole = $user->hasRole('teacher') || $user->hasRole('user');
        $hasUploadAccess = (Option::resourceUploadsEnabled() && $this->resourceUploadWindow->isOpen())
            || CriterionUploadPermission::query()
                ->available()
                ->whereBelongsTo($user)
                ->whereBelongsTo($criterion)
                ->exists();

        return ! $user->isUploadBlocked()
            && $hasUploadAccess
            && ($hasTeacherRole || $user->isSuperAdmin())
            && ($criterion->upload === '1' || $criterion->isHIndexCriterion())
            && $criterion->status === '1'
            && $criterion->report()->where('status', '1')->exists()
            && $criterion->criterionEvaluations()
                ->where('evaluation', $user->degree)
                ->where('has', '1')
                ->exists();
    }

    public function update(User $user, Criterion $criterion): bool
    {
        return $user->isSuperAdmin();
    }
}
