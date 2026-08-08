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
use App\Support\EducationalContentCriterionRule;
use App\Support\ForeignLanguageCertificateCriterionRule;
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
        Request $request,
        Datum $datum,
        TransferDatumCriterion $transferDatumCriterion,
        OakArticleScoreCalculator $oakArticleScoreCalculator,
    ): View {
        $isAcceptedScoreCorrection = $request->user()?->can('correctAcceptedScore', $datum) === true;
        abort_unless(
            $isAcceptedScoreCorrection || $request->user()?->can('review', $datum) === true,
            403,
        );

        $reviewIndexRoute = $this->reviewIndexRoute($datum);
        $reviewReturnUrl = $datum->usesAiChecking()
            && $request->user()?->can('manage-ai-operations') === true
            ? route('ai-status.index')
            : route($reviewIndexRoute);
        $reviewQueue = $datum->usesAiChecking() ? 'ai' : 'manual';
        $datum->load([
            'user:id,name,hemis_id,degree',
            'user.ratingWorkplace.department:id,parent_id',
            'criterion:id,code,name,desc,checking,formula_id,report_id',
            'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            'criterion.manualScoreOptions',
            'year:id,name',
            'histories' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);
        $status = DatumStatus::from($datum->status);
        $scoreOptions = $datum->criterion?->manualScoreOptions ?? collect();
        if ($datum->criterion?->code === EducationalContentCriterionRule::CODE) {
            $usedScoreOptionIds = Datum::query()
                ->where('user_id', $datum->user_id)
                ->where('criterion_id', $datum->criterion_id)
                ->where('status', DatumStatus::Accepted->value)
                ->whereNotNull('manual_score_option_id')
                ->pluck('manual_score_option_id');
            $scoreOptions = $scoreOptions
                ->whereNotIn('id', $usedScoreOptionIds)
                ->values();
        }
        $evaluationMaximum = (float) $datum->criterion?->criterionEvaluations
            ->firstWhere('evaluation', $datum->user?->degree)?->score;
        $educationalContentScoring = $datum->criterion?->code === EducationalContentCriterionRule::CODE
            ? [
                'category' => match ($datum->user?->degree) {
                    'hold_degrees' => 'Ilmiy darajali',
                    'foreign_lang' => 'Chet tillari yo‘nalishi',
                    'physical' => 'Jismoniy madaniyat yo‘nalishi',
                    default => 'Ilmiy darajasiz',
                },
                'maximum' => $evaluationMaximum,
                'options' => $scoreOptions->mapWithKeys(function ($option) use ($evaluationMaximum): array {
                    return [$option->getKey() => [
                        'percentage' => EducationalContentCriterionRule::percentageFor($option->code),
                        'point' => EducationalContentCriterionRule::pointFor($evaluationMaximum, $option->code),
                    ]];
                })->all(),
            ]
            : null;
        $foreignLanguageCertificateScoring = $datum->criterion?->isForeignLanguageCertificateCriterion() === true
            ? [
                'special_department' => ForeignLanguageCertificateCriterionRule::isSpecialForeignLanguageDepartment(
                    $datum->user?->ratingWorkplace?->department?->getKey(),
                    $datum->user?->ratingWorkplace?->department?->parent_id,
                ),
                'options' => $scoreOptions->mapWithKeys(fn ($option): array => [
                    $option->getKey() => ForeignLanguageCertificateCriterionRule::pointFor(
                        $option->code,
                        (string) $datum->user?->degree,
                        $datum->user?->ratingWorkplace?->department?->getKey(),
                        $datum->user?->ratingWorkplace?->department?->parent_id,
                    ),
                ])->all(),
            ]
            : null;
        $oakArticleBasePoint = $datum->criterion?->isOakArticleCriterion() === true
            ? $oakArticleScoreCalculator->basePoint($datum->user?->degree ?? '')
            : null;
        $transferCriteria = $isAcceptedScoreCorrection
            ? collect()
            : $transferDatumCriterion->destinations($datum);
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            [
                'url' => $reviewReturnUrl,
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
            'educationalContentScoring',
            'isAcceptedScoreCorrection',
            'reviewReturnUrl',
            'foreignLanguageCertificateScoring',
        ));
    }

    public function approve(
        ApproveDatumRequest $request,
        Datum $datum,
        ReviewDatumSubmission $action,
    ): RedirectResponse {
        $isAcceptedScoreCorrection = $request->user()?->can('correctAcceptedScore', $datum) === true;
        $reviewIndexRoute = $this->reviewIndexRoute($datum);

        $action->approve(
            $request->user(),
            $datum,
            $request->validated('score_option_id'),
            $request->filled('point') ? $request->float('point') : null,
            $request->filled('author_count') ? $request->integer('author_count') : null,
            $request->filled('page_count') ? $request->integer('page_count') : null,
            $request->filled('impact_factor') ? $request->integer('impact_factor') : null,
            $request->filled('publication_tier') ? $request->string('publication_tier')->toString() : null,
            $request->filled('university_tier') ? $request->string('university_tier')->toString() : null,
            $request->filled('received_amount') ? $request->float('received_amount') : null,
        );

        if ($isAcceptedScoreCorrection) {
            return redirect()
                ->route('upload.details', $datum)
                ->with('success', 'Tasdiqlangan resurs balli server qoidasi bo‘yicha qayta hisoblandi.');
        }

        if ($datum->usesAiChecking()
            && $request->user()?->can('manage-ai-operations') === true) {
            return redirect()
                ->route('upload.details', $datum)
                ->with('success', 'Resurs tasdiqlandi va ball hisoblandi.');
        }

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
