<?php

namespace App\Http\Controllers;

use App\Actions\GetRatingIndexData;
use App\Actions\GetUserRatingDetails;
use App\Http\Requests\RatingFilterRequest;
use App\Models\User;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function index(RatingFilterRequest $request, GetRatingIndexData $getRatingIndexData): View
    {
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Reyting',
            ],
        ];

        return view('pages.ratings.index', [
            ...$getRatingIndexData->handle($request->validated()),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function show(
        RatingFilterRequest $request,
        User $user,
        GetUserRatingDetails $getUserRatingDetails,
    ): View {
        $details = $getUserRatingDetails->handle($user);
        $filters = $request->validated();
        $breadcrumbs = [
            ['url' => route('ratings.index', $filters), 'name' => 'Reyting'],
            ['url' => '#', 'name' => $user->full ?: 'Foydalanuvchi'],
        ];

        return view('pages.ratings.show', [...$details, 'breadcrumbs' => $breadcrumbs, 'filters' => $filters]);
    }
}
