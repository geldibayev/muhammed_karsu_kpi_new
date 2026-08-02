<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Datum;
use Illuminate\Support\Collection;

class GetCriterionResourceStatistics
{
    /**
     * @param  Collection<int, int>  $criterionIds
     * @return Collection<int, array{total: int, checked: int, unchecked: int, returned: int, deleted: int, other: int}>
     */
    public function handle(Collection $criterionIds): Collection
    {
        $criterionIds = $criterionIds
            ->map(static fn (mixed $criterionId): int => (int) $criterionId)
            ->unique()
            ->values();

        $emptyStatistics = $criterionIds->mapWithKeys(static fn (int $criterionId): array => [
            $criterionId => [
                'total' => 0,
                'checked' => 0,
                'unchecked' => 0,
                'returned' => 0,
                'deleted' => 0,
                'other' => 0,
            ],
        ]);

        if ($criterionIds->isEmpty()) {
            return $emptyStatistics;
        }

        $rows = Datum::query()
            ->toBase()
            ->select('criterion_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS checked', [DatumStatus::Accepted->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS unchecked', [
                DatumStatus::Received->value,
                DatumStatus::Checking->value,
            ])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS returned', [DatumStatus::Cancelled->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS deleted', [DatumStatus::Deleted->value])
            ->whereIn('criterion_id', $criterionIds)
            ->groupBy('criterion_id')
            ->get()
            ->mapWithKeys(static function (object $row): array {
                $total = (int) $row->total;
                $checked = (int) $row->checked;
                $unchecked = (int) $row->unchecked;
                $returned = (int) $row->returned;
                $deleted = (int) $row->deleted;

                return [
                    (int) $row->criterion_id => [
                        'total' => $total,
                        'checked' => $checked,
                        'unchecked' => $unchecked,
                        'returned' => $returned,
                        'deleted' => $deleted,
                        'other' => max(0, $total - $checked - $unchecked - $returned - $deleted),
                    ],
                ];
            });

        return $emptyStatistics->replace($rows);
    }
}
