<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetCriterionResourceStatisticsTable
{
    /**
     * @param  array{sort?: string, direction?: string}  $filters
     * @return Collection<int, Criterion>
     */
    public function handle(array $filters): Collection
    {
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id']);

        if ($report === null) {
            return new Collection;
        }

        $sort = $filters['sort'] ?? null;
        $direction = $filters['direction'] ?? 'desc';

        return Criterion::query()
            ->select(['id', 'code', 'name', 'parent_id', 'sort_order'])
            ->whereBelongsTo($report)
            ->whereNotNull('parent_id')
            ->where('status', '1')
            ->whereHas('parent', fn (Builder $query): Builder => $query->where('status', '1'))
            ->with('parent:id,name,sort_order')
            ->withCount([
                'files as total',
                'files as checked' => fn (Builder $query): Builder => $query
                    ->where('status', DatumStatus::Accepted->value),
                'files as unchecked' => fn (Builder $query): Builder => $query
                    ->whereIn('status', [
                        DatumStatus::Received->value,
                        DatumStatus::Checking->value,
                    ]),
                'files as returned' => fn (Builder $query): Builder => $query
                    ->where('status', DatumStatus::Cancelled->value),
                'files as deleted' => fn (Builder $query): Builder => $query
                    ->where('status', DatumStatus::Deleted->value),
                'files as other' => fn (Builder $query): Builder => $query
                    ->whereNotIn('status', array_column(DatumStatus::cases(), 'value')),
            ])
            ->when(
                $sort !== null,
                fn (Builder $query): Builder => $query->orderBy($sort, $direction),
            )
            ->orderBy(
                Criterion::query()
                    ->from('criteria as parent_criteria')
                    ->select('sort_order')
                    ->whereColumn('parent_criteria.id', 'criteria.parent_id'),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
