<?php

namespace App\Http\Controllers;

use App\Actions\GetAiHumanReviewerStatistics;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiHumanReviewerStatisticsController extends Controller
{
    public function __invoke(GetAiHumanReviewerStatistics $getStatistics): View
    {
        Gate::authorize('view-ai-human-reviewer-statistics');

        return view('pages.ai-human-reviewer-statistics.index', [
            'statistics' => $getStatistics->handle(),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'AI mas’ullar statistikasi'],
            ],
        ]);
    }
}
