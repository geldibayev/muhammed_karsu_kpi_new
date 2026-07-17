<?php

namespace App\Policies;

use App\Models\Criterion;
use App\Models\User;

class CriterionPolicy
{
    public function submit(User $user, Criterion $criterion): bool
    {
        $hasTeacherRole = $user->hasRole('teacher') || $user->hasRole('user');

        return ($hasTeacherRole || $user->isSuperAdmin())
            && $criterion->upload === '1'
            && $criterion->status === '1';
    }

    public function update(User $user, Criterion $criterion): bool
    {
        return $user->isSuperAdmin();
    }
}
