<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

class GetAiReviewerHealth
{
    /**
     * @return array{
     *     state: 'operational'|'processing'|'degraded'|'unavailable'|'unknown',
     *     checked_at: CarbonInterface|null,
     *     reason: string|null,
     *     pending_resources: int,
     *     waiting_resources: int,
     *     failed_pending_resources: int,
     *     oldest_waiting_at: CarbonInterface|null
     * }
     */
    public function handle(): array
    {
        $aiDatumIds = Datum::query()
            ->select('data.id')
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->where('criteria.checking', 'ai');

        $latestCheck = DatumHistory::query()
            ->select(['message', 'message_type', 'created_at'])
            ->whereIn('datum_id', $aiDatumIds)
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        $aiHistorySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN 1 ELSE 0 END) AS has_evaluation")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN 1 ELSE 0 END) AS has_failure")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->groupBy('datum_id');

        $pendingAggregate = Datum::query()
            ->toBase()
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->leftJoinSub($aiHistorySummary->toBase(), 'ai_history', function (Builder $join): void {
                $join->on('ai_history.datum_id', '=', 'data.id');
            })
            ->where('criteria.checking', 'ai')
            ->whereIn('data.status', ['received', 'checking'])
            ->selectRaw('COUNT(*) AS pending_resources')
            ->selectRaw('SUM(CASE WHEN COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 0 THEN 1 ELSE 0 END) AS waiting_resources')
            ->selectRaw('SUM(CASE WHEN COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 1 THEN 1 ELSE 0 END) AS failed_pending_resources')
            ->selectRaw('MIN(CASE WHEN COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 0 THEN data.created_at END) AS oldest_waiting_at')
            ->first();

        $pendingResources = (int) ($pendingAggregate->pending_resources ?? 0);
        $waitingResources = (int) ($pendingAggregate->waiting_resources ?? 0);
        $failedPendingResources = (int) ($pendingAggregate->failed_pending_resources ?? 0);
        $oldestWaitingAt = $this->toDate($pendingAggregate->oldest_waiting_at ?? null);
        $staleAfterMinutes = max(1, (int) config('kpi.ai_queue_stale_after_minutes', 10));
        $hasStaleQueue = $oldestWaitingAt?->lte(now()->subMinutes($staleAfterMinutes)) ?? false;

        $state = match (true) {
            $hasStaleQueue => 'unavailable',
            $latestCheck?->message_type === 'ai_failed' => 'unavailable',
            $failedPendingResources > 0 => 'degraded',
            $waitingResources > 0 => 'processing',
            $latestCheck?->message_type === 'ai_evaluation' => 'operational',
            default => 'unknown',
        };

        $reason = match (true) {
            $hasStaleQueue => "{$waitingResources} ta resurs {$oldestWaitingAt->format('d.m.Y H:i')} dan beri navbatda. AI navbat ishlovchisi javob bermayapti.",
            $latestCheck?->message_type === 'ai_failed' => $latestCheck->message,
            $failedPendingResources > 0 => "{$failedPendingResources} ta resurs AI xatosidan keyin inson ko‘rigini kutmoqda.",
            $waitingResources > 0 => "{$waitingResources} ta resurs AI tekshiruv navbatida.",
            default => null,
        };

        return [
            'state' => $state,
            'checked_at' => $latestCheck?->created_at,
            'reason' => $reason,
            'pending_resources' => $pendingResources,
            'waiting_resources' => $waitingResources,
            'failed_pending_resources' => $failedPendingResources,
            'oldest_waiting_at' => $oldestWaitingAt,
        ];
    }

    private function toDate(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }
}
