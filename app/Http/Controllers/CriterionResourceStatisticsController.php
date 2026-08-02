<?php

namespace App\Http\Controllers;

use App\Actions\GetCriterionResourceStatisticsTable;
use App\Http\Requests\CriterionResourceStatisticsRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CriterionResourceStatisticsController extends Controller
{
    public function __invoke(
        CriterionResourceStatisticsRequest $request,
        GetCriterionResourceStatisticsTable $getCriterionResourceStatisticsTable,
    ): View {
        Gate::authorize('view-resource-statistics');

        $filters = $request->validated();

        return view('pages.statistics.criteria', [
            'criteria' => $getCriterionResourceStatisticsTable->handle($filters),
            'sort' => $filters['sort'] ?? null,
            'direction' => $filters['direction'] ?? 'desc',
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'Kriteriyalar statistikasi'],
            ],
        ]);
    }
}
