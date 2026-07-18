<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function index(): View
    {
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $users = User::query()
            ->select(['id', 'name', 'pos'])
            ->withSum([
                'points as total_points' => function (Builder $query) use ($report): void {
                    $query->when(
                        $report !== null,
                        fn (Builder $query): Builder => $query->where('report_id', $report->getKey()),
                        fn (Builder $query): Builder => $query->whereNull('report_id'),
                    );
                },
            ], 'point')
            ->orderByDesc('total_points')
            ->orderBy('id')
            ->paginate(25);

        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Reyting',
            ],
        ];

        return view('pages.ratings.index', compact('breadcrumbs', 'report', 'users'));
    }
}
