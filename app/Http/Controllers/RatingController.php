<?php

namespace App\Http\Controllers;

use App\Actions\GetUserRatingDetails;
use App\Actions\PaginateRatingUsers;
use App\Http\Requests\RatingFilterRequest;
use App\Models\Department;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function index(RatingFilterRequest $request, PaginateRatingUsers $paginateRatingUsers): View
    {
        $filters = $request->validated();
        $filters['degree_group'] ??= 'with_degree';

        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $users = $paginateRatingUsers->handle($report, $filters);
        $faculties = Department::query()
            ->select(['id', 'name'])
            ->whereNull('parent_id')
            ->orderBy('name->uz')
            ->get();
        $departments = Department::query()
            ->select(['id', 'name', 'parent_id'])
            ->whereNotNull('parent_id')
            ->when(
                $filters['faculty'] ?? null,
                fn (Builder $query, int $facultyId): Builder => $query->where('parent_id', $facultyId),
            )
            ->orderBy('name->uz')
            ->get();

        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Reyting',
            ],
        ];

        return view('pages.ratings.index', compact(
            'breadcrumbs',
            'departments',
            'faculties',
            'filters',
            'report',
            'users',
        ));
    }

    public function show(
        RatingFilterRequest $request,
        User $user,
        GetUserRatingDetails $getUserRatingDetails,
    ): View {
        $details = $getUserRatingDetails->handle($user);
        $filters = $request->validated();
        $breadcrumbs = [
            ['url' => route('ratings.index', $filters), 'name' => 'Reyting'],
            ['url' => '#', 'name' => $user->full ?: 'Foydalanuvchi'],
        ];

        return view('pages.ratings.show', [...$details, 'breadcrumbs' => $breadcrumbs, 'filters' => $filters]);
    }
}
