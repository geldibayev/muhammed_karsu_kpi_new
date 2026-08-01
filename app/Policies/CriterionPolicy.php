<?php

namespace App\Policies;

use App\Models\Criterion;
use App\Models\Option;
use App\Models\User;

class CriterionPolicy
{
    public function submit(User $user, Criterion $criterion): bool
    {
        $hasTeacherRole = $user->hasRole('teacher') || $user->hasRole('user');

        return Option::resourceUploadsEnabled()
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
