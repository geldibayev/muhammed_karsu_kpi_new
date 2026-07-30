<?php

namespace App\Http\Controllers;

use App\Enums\DatumStatus;
use App\Models\Datum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiHumanReviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->can('access-ai-human-reviews'), 403);

        $pendingSubmissions = Datum::query()
            ->where('reviewer_hemis_id', $user->hemis_id)
            ->where('status', DatumStatus::Checking->value)
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            )
            ->with(['user:id,name,hemis_id,degree', 'criterion:id,name', 'year:id,name'])
            ->latest()
            ->paginate(20);
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'AI inson tekshiruvi'],
        ];

        return view('pages.ai-human-reviews.index', compact('pendingSubmissions', 'breadcrumbs'));
    }
}
