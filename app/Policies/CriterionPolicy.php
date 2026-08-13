<?php

namespace App\Policies;

use App\Models\Criterion;
use App\Models\Option;
use App\Models\User;
use App\Support\ResourceUploadWindow;

class CriterionPolicy
{
    public function __construct(private ResourceUploadWindow $resourceUploadWindow) {}

    public function submit(User $user, Criterion $criterion): bool
    {
        $hasTeacherRole = $user->hasRole('teacher') || $user->hasRole('user');

        return ! $user->isUploadBlocked()
            && Option::resourceUploadsEnabled()
            && $this->resourceUploadWindow->isOpen()
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
