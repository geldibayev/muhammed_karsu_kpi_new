<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Option;
use App\Models\Point;
use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $degree = $request->user()->degree;
        $report = Report::query()->where('status', '1')->latest('id')->first();
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
                'children.reviewerAssignment.user:id,hemis_id,name',
                'children.criterionEvaluations' => fn (HasMany $query): HasMany => $query
                    ->where('evaluation', $degree)
                    ->where('has', '1'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $points = $report === null
            ? collect()
            : Point::query()
                ->whereBelongsTo($request->user())
                ->whereBelongsTo($report)
                ->pluck('point', 'criterion_id');
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa',
            ],
        ];
        $resourceUploadsEnabled = Option::resourceUploadsEnabled();

        return view('home', compact(['criteria', 'points', 'breadcrumbs', 'resourceUploadsEnabled']));
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
