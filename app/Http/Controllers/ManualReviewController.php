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
use App\Services\OakArticleScoreCalculator;
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
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', '!=', 'ai'),
            )
            ->orderBy('criterion_code');

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

        return view('pages.reviews.index', compact(
            'assignments',
            'pendingSubmissions',
            'breadcrumbs',
        ));
    }

    public function show(
        Datum $datum,
        TransferDatumCriterion $transferDatumCriterion,
        OakArticleScoreCalculator $oakArticleScoreCalculator,
    ): View {
        $this->authorize('review', $datum);

        $reviewIndexRoute = $this->reviewIndexRoute($datum);
        $reviewQueue = $datum->usesAiChecking() ? 'ai' : 'manual';
        $datum->load([
            'user:id,name,hemis_id,degree',
            'criterion:id,code,name,desc,checking,formula_id,report_id',
            'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            'criterion.manualScoreOptions',
            'year:id,name',
            'histories' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);
        $status = DatumStatus::from($datum->status);
        $scoreOptions = $datum->criterion?->manualScoreOptions ?? collect();
        $oakArticleBasePoint = $datum->criterion?->isOakArticleCriterion() === true
            ? $oakArticleScoreCalculator->basePoint($datum->user?->degree ?? '')
            : null;
        $transferCriteria = $transferDatumCriterion->destinations($datum);
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            [
                'url' => route($reviewIndexRoute),
                'name' => $reviewQueue === 'ai' ? 'AI inson tekshiruvi' : 'Baholash',
            ],
            ['url' => '#', 'name' => 'Resurs #'.$datum->id],
        ];

        return view('pages.reviews.show', compact(
            'datum',
            'status',
            'scoreOptions',
            'transferCriteria',
            'breadcrumbs',
            'reviewIndexRoute',
            'reviewQueue',
            'oakArticleBasePoint',
        ));
    }

    public function approve(
        ApproveDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $reviewIndexRoute = $this->reviewIndexRoute($datum);

        $action->approve(
            $request->user(),
            $datum,
            $request->validated('score_option_id'),
            $request->filled('point') ? $request->float('point') : null,
            $request->filled('author_count') ? $request->integer('author_count') : null,
        );

        return redirect()->route($reviewIndexRoute)->with('success', 'Resurs tasdiqlandi va ball hisoblandi.');
    }

    public function reject(
        RejectDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $reviewIndexRoute = $this->reviewIndexRoute($datum);

        $action->reject($request->user(), $datum, $request->validated('reason'));

        return redirect()->route($reviewIndexRoute)->with('success', 'Resurs sabab ko‘rsatilgan holda qaytarildi.');
    }

    public function transferCriterion(
        TransferDatumCriterionRequest $request,
        Datum $datum,
        TransferDatumCriterion $action,
    ): RedirectResponse {
        $reviewIndexRoute = $this->reviewIndexRoute($datum);

        $action->handle($request->user(), $datum, $request->integer('criterion_id'));

        return redirect()->route($reviewIndexRoute)->with('success', 'Resurs boshqa kriteriyaga o‘tkazildi.');
    }

    private function reviewIndexRoute(Datum $datum): string
    {
        return $datum->usesAiChecking()
            ? 'ai-human-reviews.index'
            : 'reviews.index';
    }
}
