<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewerAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('manage-reviewer-assignments'), 403);

        $criteria = Criterion::query()
            ->whereNotNull('parent_id')
            ->where('checking', '!=', 'ai')
            ->with(['reviewerAssignment.user:id,hemis_id,name'])
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();
        $parentNumbers = Criterion::query()
            ->whereNull('parent_id')
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->flip()
            ->map(fn (int $index): int => $index + 1);

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Ma’sullar'],
        ];

        return view('pages.admin.reviewers.index', compact('criteria', 'parentNumbers', 'breadcrumbs'));
    }
}
