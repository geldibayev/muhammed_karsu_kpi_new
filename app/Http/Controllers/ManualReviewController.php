<?php

namespace App\Http\Controllers;

use App\Actions\ReviewDatumSubmission;
use App\Enums\DatumStatus;
use App\Http\Requests\ApproveDatumRequest;
use App\Http\Requests\RejectDatumRequest;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->can('access-manual-reviews'), 403);

        $assignmentsQuery = CriterionReviewerAssignment::query()
            ->with('criterion:id,name,checking,status')
            ->orderBy('criterion_code');

        if (! $user->isSuperAdmin()) {
            $assignmentsQuery->where('hemis_id', $user->hemis_id);
        }

        $assignments = $assignmentsQuery->get();
        $pendingSubmissions = Datum::query()
            ->whereIn('criterion_id', $assignments->pluck('criterion_id'))
            ->whereIn('status', [DatumStatus::Received->value, DatumStatus::Checking->value])
            ->with(['user:id,name,hemis_id,degree', 'criterion:id,name', 'year:id,name'])
            ->latest()
            ->paginate(20);

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Baholash'],
        ];

        return view('pages.reviews.index', compact('assignments', 'pendingSubmissions', 'breadcrumbs'));
    }

    public function show(Datum $datum): View
    {
        $this->authorize('review', $datum);

        $datum->load([
            'user:id,name,hemis_id,degree',
            'criterion:id,name',
            'year:id,name',
            'histories' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);
        $status = DatumStatus::from($datum->status);
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => route('reviews.index'), 'name' => 'Baholash'],
            ['url' => '#', 'name' => 'Resurs #'.$datum->id],
        ];

        return view('pages.reviews.show', compact('datum', 'status', 'breadcrumbs'));
    }

    public function approve(
        ApproveDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $action->approve($request->user(), $datum);

        return redirect()->route('reviews.index')->with('success', 'Resurs tasdiqlandi va ball hisoblandi.');
    }

    public function reject(
        RejectDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $action->reject($request->user(), $datum, $request->validated('reason'));

        return redirect()->route('reviews.index')->with('success', 'Resurs sabab ko‘rsatilgan holda qaytarildi.');
    }
}
