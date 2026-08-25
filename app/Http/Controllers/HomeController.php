<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\CriterionUploadPermission;
use App\Models\Datum;
use App\Models\Option;
use App\Models\Point;
use App\Models\Report;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\RatingMethodPresenter;
use App\Support\ResourceUploadWindow;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(
        Request $request,
        RatingMethodPresenter $ratingMethodPresenter,
        ResourceUploadWindow $resourceUploadWindow,
    ): View {
        $degree = $request->user()->degree;
        $report = Report::current();
        $criteria = Criterion::query()
            ->whereNull('parent_id')
            ->where('report_id', $report?->getKey() ?? 0)
            ->where('status', '1')
            ->whereHas('report', fn ($query) => $query->where('status', '1'))
            ->with([
                'children' => fn (HasMany $query): HasMany => $query
                    ->where('status', '1')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'children.formula:id,code,name',
                'children.reviewerAssignment.user:id,hemis_id,name',
                'children.criterionEvaluations' => fn (HasMany $query): HasMany => $query
                    ->where('evaluation', $degree)
                    ->where('has', '1'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $ratingMethods = $criteria
            ->flatMap(fn (Criterion $criterion) => $criterion->children)
            ->mapWithKeys(fn (Criterion $criterion): array => [
                $criterion->getKey() => $ratingMethodPresenter->describe($criterion, $degree),
            ]);
        $fourOneOneCriterion = $criteria
            ->flatMap(fn (Criterion $criterion) => $criterion->children)
            ->firstWhere('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE);
        $fourOneOneReplacementDatum = $fourOneOneCriterion === null
            ? null
            : Datum::query()
                ->whereBelongsTo($request->user())
                ->whereBelongsTo($fourOneOneCriterion)
                ->where('status', 'cancelled')
                ->whereDoesntHave(
                    'histories',
                    fn ($query) => $query->where('message_type', 'four_one_one_reference_replacement_submitted'),
                )
                ->latest('id')
                ->get(['id', 'user_id', 'criterion_id', 'status'])
                ->first(fn (Datum $datum): bool => $request->user()->can('replaceFourOneOneReference', $datum));
        $points = $report === null
            ? collect()
            : Point::query()
                ->whereBelongsTo($request->user())
                ->forRatingReport($report)
                ->pluck('point', 'criterion_id');
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa',
            ],
        ];
        $resourceUploadWindowOpen = $resourceUploadWindow->isOpen();
        $resourceUploadsEnabled = Option::resourceUploadsEnabled()
            && $resourceUploadWindowOpen
            && ! $request->user()->isUploadBlocked();
        $uploadPermissionCriterionIds = $request->user()->isUploadBlocked()
            ? collect()
            : CriterionUploadPermission::query()
                ->available()
                ->whereBelongsTo($request->user())
                ->pluck('criterion_id');

        return view('home', compact([
            'criteria',
            'points',
            'breadcrumbs',
            'resourceUploadsEnabled',
            'resourceUploadWindowOpen',
            'ratingMethods',
            'fourOneOneReplacementDatum',
            'uploadPermissionCriterionIds',
        ]));
    }

    public function profile()
    {
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa',
            ],
        ];

        $user = auth()->user()->load([
            'ratingWorkplace.academic_degree',
            'ratingWorkplace.academic_rank',
            'workplaces.department',
            'workplaces.staff',
            'workplaces.form',
            'workplaces.position',
            'workplaces.status',
        ]);

        $workpl = $user->ratingWorkplace;

        return view('pages.users.profile', compact(['breadcrumbs', 'workpl', 'user']));
    }

    public function logout()
    {
        auth()->logout();

        return redirect()->route('login');
    }
}
