<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCriterionRating
{
    /** @return LengthAwarePaginator<int, Point> */
    public function handle(Criterion $criterion): LengthAwarePaginator
    {
        return Point::query()
            ->select(['id', 'user_id', 'criterion_id', 'report_id', 'point'])
            ->whereBelongsTo($criterion)
            ->where('report_id', $criterion->report_id)
            ->with([
                'user' => function (BelongsTo $query) use ($criterion): void {
                    $query->select(['id', 'hemis_id', 'name', 'image'])
                        ->withCount([
                            'submissions as accepted_submissions_count' => fn (Builder $query): Builder => $query
                                ->whereBelongsTo($criterion)
                                ->where('status', DatumStatus::Accepted->value),
                        ]);
                },
                'user.ratingWorkplace.position',
                'user.ratingWorkplace.department.parent',
            ])
            ->orderByDesc('point')
            ->orderBy('user_id')
            ->paginate(50);
    }
}
