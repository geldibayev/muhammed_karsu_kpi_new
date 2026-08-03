<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillCriterionThreeOneFifteenPoints
{
    public function count(Report $report): int
    {
        return $this->candidateQuery($report)->count();
    }

    public function handle(Report $report): int
    {
        $updatedCount = 0;

        foreach ($this->candidateQuery($report)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if ($this->updatePoint($datum->getKey(), $report)) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    private function candidateQuery(Report $report): Builder
    {
        return Datum::query()
            ->select(['data.id'])
            ->where('data.status', 'accepted')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('data.point')
                ->orWhere('data.point', '<', 2))
            ->whereHas('user', fn (Builder $query): Builder => $query
                ->where('degree', 'hold_degrees'))
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', '3.1.15'));
    }

    private function updatePoint(int $datumId, Report $report): bool
    {
        return DB::transaction(function () use ($datumId, $report): bool {
            $datum = Datum::query()
                ->with(['user:id,degree', 'criterion:id,code,report_id'])
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->user?->degree !== 'hold_degrees'
                || $datum->criterion?->code !== '3.1.15'
                || $datum->criterion->report_id !== $report->getKey()
                || ($datum->point !== null && $datum->point >= 2)) {
                return false;
            }

            $oldPoint = $datum->point;
            $oldPointLabel = $oldPoint === null
                ? 'mavjud emas'
                : number_format($oldPoint, 4, '.', '');
            $datum->update(['point' => 2]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '3.1.15 kriteriyasi bo‘yicha ball yangi qoidaga muvofiq 2 ballga tenglandi. '
                    .'Oldingi ball: '.$oldPointLabel.'. Yangi ball: 2.0000.',
                'message_type' => 'criterion_3_1_15_point_corrected',
            ]);

            return true;
        }, 3);
    }
}
