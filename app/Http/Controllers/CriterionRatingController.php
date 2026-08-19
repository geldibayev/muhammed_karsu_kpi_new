<?php

namespace App\Http\Controllers;

use App\Actions\GetCriterionRating;
use App\Http\Requests\RatingFilterRequest;
use App\Models\Criterion;
use Illuminate\View\View;

class CriterionRatingController extends Controller
{
    public function __invoke(
        RatingFilterRequest $request,
        Criterion $criterion,
        GetCriterionRating $getCriterionRating,
    ): View {
        abort_if($criterion->parent_id === null || $criterion->status !== '1', 404);

        $selectedSort = $request->validated('sort') ?: 'point_desc';
        $criterion->load('report:id,name');
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Kriteriya reytingi'],
        ];

        return view('pages.ratings.criterion', [
            'criterion' => $criterion,
            'rankedPoints' => $getCriterionRating->handle($criterion, $selectedSort),
            'selectedSort' => $selectedSort,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
