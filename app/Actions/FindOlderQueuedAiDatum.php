<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DatumHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FindOlderQueuedAiDatum
{
    public function handle(Datum $currentDatum): ?Datum
    {
        $datumTable = (new Datum)->getTable();
        $criterionTable = (new Criterion)->getTable();
        $historySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed', 'submission_created', 'ai_queued'])
            ->groupBy('datum_id');

        return Datum::query()
            ->select([
                "{$datumTable}.id",
                "{$datumTable}.criterion_id",
                "{$datumTable}.created_at",
            ])
            ->join(
                $criterionTable,
                "{$criterionTable}.id",
                '=',
                "{$datumTable}.criterion_id",
            )
            ->joinSub($historySummary->toBase(), 'ai_history', function (QueryBuilder $join) use (
                $datumTable,
            ): void {
                $join->on('ai_history.datum_id', '=', "{$datumTable}.id");
            })
            ->where("{$datumTable}.status", 'checking')
            ->where("{$criterionTable}.checking", 'ai')
            ->whereColumn('ai_history.last_queue_id', '>', 'ai_history.last_evaluation_id')
            ->whereColumn('ai_history.last_queue_id', '>', 'ai_history.last_failure_id')
            ->where(function (Builder $query) use ($currentDatum, $datumTable): void {
                $query->where("{$datumTable}.created_at", '<', $currentDatum->created_at)
                    ->orWhere(function (Builder $query) use ($currentDatum, $datumTable): void {
                        $query->where("{$datumTable}.created_at", $currentDatum->created_at)
                            ->where("{$datumTable}.id", '<', $currentDatum->getKey());
                    });
            })
            ->oldest("{$datumTable}.created_at")
            ->oldest("{$datumTable}.id")
            ->first();
    }
}
