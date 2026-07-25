<?php

namespace App\Http\Controllers;

use App\Actions\GetResourceStatusStatistics;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResourceStatisticsController extends Controller
{
    public function __invoke(GetResourceStatusStatistics $getResourceStatusStatistics): View
    {
        Gate::authorize('view-resource-statistics');

        return view('pages.statistics.index', [
            'statistics' => $getResourceStatusStatistics->handle(),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'Statistika'],
            ],
        ]);
    }
}
