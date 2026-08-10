<?php

namespace App\Policies;

use App\Models\EmploymentForm;
use App\Models\User;

class UserPolicy
{
    public function syncHemis(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteExternalPartTimer(User $user, User $externalPartTimer): bool
    {
        return $user->isSuperAdmin()
            && $user->isNot($externalPartTimer)
            && $externalPartTimer->isActive()
            && $externalPartTimer->workplaces()
                ->where('form_id', EmploymentForm::EXTERNAL_PART_TIME_ID)
                ->exists()
            && ! $externalPartTimer->primaryWorkplaces()->exists();
    }
}
