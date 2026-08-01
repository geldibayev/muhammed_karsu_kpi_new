<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ReviewerAssignmentController extends Controller
{
    public function index(): View
    {
        $criteria = Criterion::query()
            ->whereNotNull('parent_id')
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->where(function (Builder $query): void {
                $query->where('checking', '!=', 'ai')
                    ->orWhereHas('reviewerAssignment');
            })
            ->with(['reviewerAssignment.user:id,hemis_id,name'])
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Ma’sullar'],
        ];

        return view('pages.admin.reviewers.index', compact('criteria', 'breadcrumbs'));
    }
}
