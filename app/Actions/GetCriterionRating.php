<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCriterionRating
{
    /** @return LengthAwarePaginator<int, Point> */
    public function handle(Criterion $criterion, string $sort = 'point_desc'): LengthAwarePaginator
    {
        $pointTable = (new Point)->getTable();
        $datumTable = (new Datum)->getTable();
        $acceptedResourcesCount = Datum::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn("{$datumTable}.user_id", "{$pointTable}.user_id")
            ->where("{$datumTable}.criterion_id", $criterion->getKey())
            ->where("{$datumTable}.status", DatumStatus::Accepted->value);

        $query = Point::query()
            ->select(['id', 'user_id', 'criterion_id', 'report_id', 'point'])
            ->addSelect(['accepted_resources_count' => $acceptedResourcesCount])
            ->whereBelongsTo($criterion)
            ->where('report_id', $criterion->report_id)
            ->whereHas(
                'user',
                fn (Builder $query): Builder => $query->active()->academicRatingParticipants(),
            )
            ->with([
                'user' => function (BelongsTo $query) use ($criterion): void {
                    $query->select(['id', 'hemis_id', 'name', 'image'])
                        ->with([
                            'submissions' => fn (HasMany $query): HasMany => $query
                                ->select(['id', 'name', 'user_id', 'criterion_id', 'status', 'point'])
                                ->whereBelongsTo($criterion)
                                ->where('status', DatumStatus::Accepted->value)
                                ->with('latestHistory.user:id,name')
                                ->latest('id'),
                        ]);
                },
                'user.ratingWorkplace.position',
                'user.ratingWorkplace.department.parent',
            ]);

        $query = match ($sort) {
            'resources_desc' => $query->orderByDesc('accepted_resources_count')->orderByDesc('point'),
            'resources_asc' => $query->orderBy('accepted_resources_count')->orderByDesc('point'),
            default => $query->orderByDesc('point'),
        };

        return $query
            ->orderBy('user_id')
            ->paginate(50)
            ->withQueryString();
    }
}
