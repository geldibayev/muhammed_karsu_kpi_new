<?php

namespace App\Http\Controllers;

use App\Actions\GetAiReviewerDashboard;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiReviewerStatusController extends Controller
{
    public function __invoke(GetAiReviewerDashboard $getAiReviewerDashboard): View
    {
        Gate::authorize('view-ai-status');

        return view('pages.ai-status.index', [
            ...$getAiReviewerDashboard->handle(),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'AI holati'],
            ],
        ]);
    }
}
