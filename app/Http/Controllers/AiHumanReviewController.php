<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiHumanReviewFilterRequest;
use App\Models\Criterion;
use App\Models\Datum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AiHumanReviewController extends Controller
{
    public function __invoke(AiHumanReviewFilterRequest $request): View
    {
        $user = $request->user();
        $selectedCriterionId = $request->integer('criterion') ?: null;

        $criteria = Criterion::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereHas(
                'files',
                fn (Builder $query): Builder => $query->pendingAiHumanReviewFor((int) $user->hemis_id),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $pendingSubmissions = Datum::query()
            ->pendingAiHumanReviewFor((int) $user->hemis_id)
            ->when(
                $selectedCriterionId !== null,
                fn (Builder $query): Builder => $query->where('criterion_id', $selectedCriterionId),
            )
            ->with(['user:id,name,hemis_id,degree', 'criterion:id,name', 'year:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'AI inson tekshiruvi'],
        ];

        return view('pages.ai-human-reviews.index', compact(
            'pendingSubmissions',
            'criteria',
            'selectedCriterionId',
            'breadcrumbs',
        ));
    }
}
