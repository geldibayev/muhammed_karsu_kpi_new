<?php

namespace App\Actions;

use App\Models\User;

class ResolveUserEvaluationCategory
{
    public function handle(User $user): string
    {
        $ratingWorkplace = $user->ratingWorkplace()
            ->with('department:id,evaluation')
            ->first();

        if ($ratingWorkplace === null) {
            return 'no_degrees';
        }

        if ((int) $ratingWorkplace->academic_degree_id > 10) {
            return 'hold_degrees';
        }

        return $ratingWorkplace->department?->evaluation ?: 'no_degrees';
    }
}
