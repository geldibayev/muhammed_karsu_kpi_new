<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Point;
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
                'user:id,hemis_id,name,image',
                'user.ratingWorkplace.position',
                'user.ratingWorkplace.department.parent',
            ])
            ->orderByDesc('point')
            ->orderBy('user_id')
            ->paginate(50);
    }
}
