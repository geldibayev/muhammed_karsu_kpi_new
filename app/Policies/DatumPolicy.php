<?php

namespace App\Policies;

use App\Models\Datum;
use App\Models\User;

class DatumPolicy
{
    public function view(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted' && $this->ownsDatumOrIsSuperAdmin($user, $datum);
    }

    public function download(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted' && $this->ownsDatumOrIsSuperAdmin($user, $datum);
    }

    public function delete(User $user, Datum $datum): bool
    {
        return $datum->status !== 'deleted' && $this->ownsDatumOrIsSuperAdmin($user, $datum);
    }

    private function ownsDatumOrIsSuperAdmin(User $user, Datum $datum): bool
    {
        return $user->isSuperAdmin() || $datum->user_id === $user->id;
    }
}
