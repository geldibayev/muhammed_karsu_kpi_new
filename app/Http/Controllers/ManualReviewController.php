<?php

namespace App\Http\Controllers;

use App\Actions\ReviewDatumSubmission;
use App\Actions\TransferDatumCriterion;
use App\Enums\DatumStatus;
use App\Http\Requests\ApproveDatumRequest;
use App\Http\Requests\RejectDatumRequest;
use App\Http\Requests\TransferDatumCriterionRequest;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use Illuminate\Database\Eloquent\Builder;
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
            ->where('hemis_id', $user->hemis_id)
            ->orderBy('criterion_code');

        $assignments = $assignmentsQuery->get();
        $handlesAiHumanReviews = $assignments->contains(
            fn (CriterionReviewerAssignment $assignment): bool => $assignment->criterion?->checking === 'ai',
        );
        $directCriterionIds = $assignments
            ->reject(
                fn (CriterionReviewerAssignment $assignment): bool => $assignment->criterion?->checking === 'ai',
            )
            ->pluck('criterion_id');
        $pendingSubmissions = Datum::query()
            ->whereIn('status', [DatumStatus::Received->value, DatumStatus::Checking->value])
            ->where(function (Builder $query) use ($directCriterionIds, $user): void {
                $query
                    ->whereIn('criterion_id', $directCriterionIds)
                    ->orWhere('reviewer_hemis_id', $user->hemis_id);
            })
            ->with(['user:id,name,hemis_id,degree', 'criterion:id,name', 'year:id,name'])
            ->latest()
            ->paginate(20);

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Baholash'],
        ];

        return view('pages.reviews.index', compact(
            'assignments',
            'handlesAiHumanReviews',
            'pendingSubmissions',
            'breadcrumbs',
        ));
    }

    public function show(Datum $datum, TransferDatumCriterion $transferDatumCriterion): View
    {
        $this->authorize('review', $datum);

        $datum->load([
            'user:id,name,hemis_id,degree',
            'criterion:id,name,desc,checking,formula_id,report_id',
            'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            'criterion.manualScoreOptions',
            'year:id,name',
            'histories' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);
        $status = DatumStatus::from($datum->status);
        $scoreOptions = $datum->criterion?->manualScoreOptions ?? collect();
        $transferCriteria = $transferDatumCriterion->destinations($datum);
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => route('reviews.index'), 'name' => 'Baholash'],
            ['url' => '#', 'name' => 'Resurs #'.$datum->id],
        ];

        return view('pages.reviews.show', compact(
            'datum',
            'status',
            'scoreOptions',
            'transferCriteria',
            'breadcrumbs',
        ));
    }

    public function approve(
        ApproveDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $action->approve(
            $request->user(),
            $datum,
            $request->validated('score_option_id'),
            $request->filled('point') ? $request->float('point') : null,
        );

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

    public function transferCriterion(
        TransferDatumCriterionRequest $request,
        Datum $datum,
        TransferDatumCriterion $action,
    ): RedirectResponse {
        $action->handle($request->user(), $datum, $request->integer('criterion_id'));

        return redirect()->route('reviews.index')->with('success', 'Resurs boshqa kriteriyaga o‘tkazildi.');
    }
}
