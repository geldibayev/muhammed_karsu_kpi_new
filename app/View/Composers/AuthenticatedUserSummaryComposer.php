<?php

namespace App\View\Composers;

use App\Models\Point;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedUserSummaryComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $workplace = $user->ratingWorkplace()
            ->with(['academic_degree', 'academic_rank'])
            ->first();
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id']);
        $totalPoints = $report === null
            ? 0.0
            : (float) Point::query()
                ->whereBelongsTo($user)
                ->forRatingReport($report)
                ->sum('point');

        $view->with([
            'layoutUser' => $user,
            'layoutWorkplace' => $workplace,
            'layoutTotalPoints' => $totalPoints,
        ]);
    }
}
