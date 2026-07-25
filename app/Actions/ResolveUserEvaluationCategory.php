<?php

namespace App\Actions;

use App\Models\User;

class ResolveUserEvaluationCategory
{
    public function handle(User $user): string
    {
        $primaryWorkplace = $user->primaryWorkplace()
            ->with('department:id,evaluation')
            ->first();

        if ($primaryWorkplace === null) {
            return 'no_degrees';
        }

        if ((int) $primaryWorkplace->academic_degree_id > 10) {
            return 'hold_degrees';
        }

        return $primaryWorkplace->department?->evaluation ?: 'no_degrees';
    }
}
