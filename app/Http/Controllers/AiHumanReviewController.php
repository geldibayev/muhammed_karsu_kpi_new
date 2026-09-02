<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiHumanReviewFilterRequest;
use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\Datum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AiHumanReviewController extends Controller
{
    public function __invoke(AiHumanReviewFilterRequest $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $selectedCriterionId = $request->integer('criterion') ?: null;
        $selectedStatus = $request->validated('status') ?: 'pending';
        $assignedCriterionCodes = AiHumanReviewAssignment::criterionCodesFor((int) $user->hemis_id);

        $resourceScope = function (Builder $query) use (
            $assignedCriterionCodes,
            $isSuperAdmin,
            $selectedStatus,
            $user,
        ): Builder {
            if ($selectedStatus === 'pending') {
                return $isSuperAdmin
                    ? $query->pendingAiHumanReviews((int) $user->hemis_id)
                    : $query->pendingAiHumanReviewFor((int) $user->hemis_id);
            }

            return $query
                ->where('status', $selectedStatus === 'scopus_audit' ? 'cancelled' : $selectedStatus)
                ->when(
                    $selectedStatus === 'scopus_audit',
                    fn (Builder $query): Builder => $query->whereHas(
                        'histories',
                        fn (Builder $query): Builder => $query
                            ->where('message_type', 'scopus_index_reference_rejected'),
                    ),
                )
                ->whereHas('criterion', function (Builder $query) use ($assignedCriterionCodes, $isSuperAdmin): void {
                    $query->where('checking', 'ai');

                    if (! $isSuperAdmin) {
                        $query->whereIn('code', $assignedCriterionCodes);
                    }
                });
        };

        $criteria = Criterion::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereHas(
                'files',
                $resourceScope,
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $submissions = Datum::query()
            ->tap($resourceScope)
            ->when(
                $selectedCriterionId !== null,
                fn (Builder $query): Builder => $query->where('criterion_id', $selectedCriterionId),
            )
            ->with([
                'user:id,name,hemis_id,degree',
                'criterion:id,name',
                'reviewer:id,name,hemis_id',
                'year:id,name',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'AI inson tekshiruvi'],
        ];

        return view('pages.ai-human-reviews.index', compact(
            'submissions',
            'criteria',
            'selectedCriterionId',
            'selectedStatus',
            'breadcrumbs',
            'isSuperAdmin',
        ));
    }
}
