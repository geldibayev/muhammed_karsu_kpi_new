<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Option;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class GetAiReviewerHealth
{
    public function __construct(private GetAiQueueMetrics $getAiQueueMetrics) {}

    /**
     * @return array{
     *     state: 'operational'|'idle'|'processing'|'recovering'|'degraded'|'unavailable'|'disabled'|'unknown',
     *     checked_at: CarbonInterface|null,
     *     reason: string|null,
     *     last_message: string|null,
     *     last_message_at: CarbonInterface|null,
     *     last_message_type: 'success'|'failure'|'status'|null,
     *     last_message_datum_id: int|null,
     *     pending_resources: int,
     *     waiting_resources: int,
     *     failed_pending_resources: int,
     *     legacy_untracked_resources: int,
     *     worker_last_seen_at: CarbonInterface|null,
     *     worker_heartbeat_at: CarbonInterface|null,
     *     worker_is_active: bool,
     *     queue_jobs: int|null,
     *     processing_jobs: int|null,
     *     orphaned_resources: int,
     *     oldest_waiting_at: CarbonInterface|null
     * }
     */
    public function handle(): array
    {
        $aiEvaluationsEnabled = Option::aiEvaluationsEnabled();
        $aiDatumIds = Datum::query()
            ->select('data.id')
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->where('criteria.checking', 'ai');

        $latestCheck = DatumHistory::query()
            ->select(['datum_id', 'message', 'message_type', 'created_at'])
            ->whereIn('datum_id', $aiDatumIds)
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        $aiHistorySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN created_at END) AS last_queued_at")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed', 'submission_created', 'ai_queued'])
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
            ->selectRaw('SUM(CASE WHEN COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_evaluation_id, 0) AND COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_failure_id, 0) THEN 1 ELSE 0 END) AS waiting_resources')
            ->selectRaw('SUM(CASE WHEN COALESCE(ai_history.last_failure_id, 0) > COALESCE(ai_history.last_evaluation_id, 0) AND COALESCE(ai_history.last_failure_id, 0) > COALESCE(ai_history.last_queue_id, 0) THEN 1 ELSE 0 END) AS failed_pending_resources')
            ->selectRaw('SUM(CASE WHEN COALESCE(ai_history.last_evaluation_id, 0) = 0 AND COALESCE(ai_history.last_failure_id, 0) = 0 AND COALESCE(ai_history.last_queue_id, 0) = 0 THEN 1 ELSE 0 END) AS legacy_untracked_resources')
            ->selectRaw('MIN(CASE WHEN COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_evaluation_id, 0) AND COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_failure_id, 0) THEN ai_history.last_queued_at END) AS oldest_waiting_at')
            ->first();

        $pendingResources = (int) ($pendingAggregate->pending_resources ?? 0);
        $waitingResources = (int) ($pendingAggregate->waiting_resources ?? 0);
        $failedPendingResources = (int) ($pendingAggregate->failed_pending_resources ?? 0);
        $legacyUntrackedResources = (int) ($pendingAggregate->legacy_untracked_resources ?? 0);
        $oldestWaitingAt = $this->toDate($pendingAggregate->oldest_waiting_at ?? null);
        $workerLastSeenAt = $this->toDate(Cache::get('kpi:ai-worker:last-seen-at'));
        $workerLastSuccessAt = $this->toDate(Cache::get('kpi:ai-worker:last-success-at'));
        $workerLastFailureAt = $this->toDate(Cache::get('kpi:ai-worker:last-failure-at'));
        $workerLastFailureReason = Cache::get('kpi:ai-worker:last-failure-reason');
        $workerLastFailureDatumId = Cache::get('kpi:ai-worker:last-failure-datum-id');
        $workerHeartbeatAt = $this->toDate(Cache::get('kpi:ai-worker:heartbeat-at'));
        $workerStaleAfterSeconds = max(75, (int) config('kpi.ai_worker_stale_after_seconds', 90));
        $workerIsActive = $workerHeartbeatAt?->gt(now()->subSeconds($workerStaleAfterSeconds)) ?? false;
        $queueMetrics = $this->getAiQueueMetrics->handle();
        $queueJobs = $queueMetrics['total'];
        $processingJobs = $queueMetrics['reserved'];
        $isQueuePaused = Queue::isPaused($queueMetrics['connection'], ProcessAiDatumEvaluation::QUEUE);
        $hasUnresolvedAttemptFailure = $workerLastFailureAt !== null
            && ($workerLastSuccessAt === null || $workerLastFailureAt->gt($workerLastSuccessAt));
        $workerLastAttemptAt = match (true) {
            $workerLastFailureAt === null => $workerLastSuccessAt,
            $workerLastSuccessAt === null => $workerLastFailureAt,
            $workerLastFailureAt->gte($workerLastSuccessAt) => $workerLastFailureAt,
            default => $workerLastSuccessAt,
        };
        $staleAfterMinutes = max(1, (int) config('kpi.ai_queue_stale_after_minutes', 10));
        $staleThreshold = now()->subMinutes($staleAfterMinutes);
        $hasOrphanedQueue = $queueMetrics['supported']
            && $waitingResources > 0
            && $queueJobs === 0
            && ($oldestWaitingAt?->lte($staleThreshold) ?? false);
        $orphanedResources = $hasOrphanedQueue ? $waitingResources : 0;
        $hasStoppedWorker = $queueMetrics['supported']
            && is_int($queueJobs)
            && $queueJobs > 0
            && ! $workerIsActive;
        $isRetryingAfterAttemptFailure = $hasUnresolvedAttemptFailure
            && is_int($queueJobs)
            && $queueJobs > 0
            && $workerIsActive;

        $state = match (true) {
            ! $aiEvaluationsEnabled => 'disabled',
            $isQueuePaused => 'unavailable',
            $isRetryingAfterAttemptFailure => 'recovering',
            $hasUnresolvedAttemptFailure => 'unavailable',
            $hasStoppedWorker => 'unavailable',
            $hasOrphanedQueue => 'recovering',
            $failedPendingResources > 0 => 'degraded',
            (is_int($queueJobs) && $queueJobs > 0) || $waitingResources > 0 => 'processing',
            $workerIsActive => 'idle',
            $latestCheck?->message_type === 'ai_evaluation' => 'operational',
            $latestCheck?->message_type === 'ai_failed' => 'degraded',
            default => 'unknown',
        };

        $reason = match (true) {
            ! $aiEvaluationsEnabled => 'AI tekshiruvi administrator tomonidan vaqtincha o\'chirilgan.',
            $isQueuePaused => 'AI tekshiruvi administrator yoki tizim tomonidan vaqtincha to‘xtatilgan.',
            $isRetryingAfterAttemptFailure => is_string($workerLastFailureReason)
                ? $workerLastFailureReason.' Tizim avtomatik qayta urinadi.'
                : 'AI urinishida xato yuz berdi. Tizim avtomatik qayta urinadi.',
            $hasUnresolvedAttemptFailure => is_string($workerLastFailureReason)
                ? $workerLastFailureReason
                : 'AI tekshiruv urinishida xato yuz berdi.',
            $hasStoppedWorker => "Navbatda {$queueJobs} ta vazifa bor, ammo avtomatik tekshiruv hozir javob bermayapti.",
            $hasOrphanedQueue => "{$orphanedResources} ta resurs navbatda uzilib qolgan. Tizim uni avtomatik qayta yuboradi.",
            $failedPendingResources > 0 => "{$failedPendingResources} ta resurs AI xatosidan keyin inson ko‘rigini kutmoqda.",
            (is_int($queueJobs) && $queueJobs > 0) || $waitingResources > 0 => "{$waitingResources} ta resurs AI tekshiruv navbatida.",
            $workerIsActive => 'Navbat bo‘sh. Yangi resurs kelishi bilan tekshiruv avtomatik boshlanadi.',
            $latestCheck?->message_type === 'ai_failed' => $latestCheck->message,
            $legacyUntrackedResources > 0 && $latestCheck === null => "{$legacyUntrackedResources} ta eski resursda AI navbat tarixi mavjud emas.",
            default => null,
        };
        $hasActualFailure = ($hasUnresolvedAttemptFailure && ! $isRetryingAfterAttemptFailure)
            || $failedPendingResources > 0
            || ($state === 'degraded' && $latestCheck?->message_type === 'ai_failed');
        $lastMessage = match (true) {
            $state === 'disabled' => $reason,
            $reason !== null => $reason,
            default => $latestCheck?->message ?? $reason,
        };
        $lastMessageAt = match (true) {
            $hasUnresolvedAttemptFailure => $workerLastFailureAt,
            $hasStoppedWorker => $workerHeartbeatAt,
            $hasOrphanedQueue => $oldestWaitingAt,
            $hasActualFailure && $latestCheck?->message_type === 'ai_failed' => $latestCheck->created_at,
            $workerIsActive => $workerHeartbeatAt,
            $state === 'operational' && $latestCheck !== null => $latestCheck->created_at,
            default => $workerLastAttemptAt ?? $latestCheck?->created_at,
        };
        $lastMessageType = match (true) {
            $hasActualFailure => 'failure',
            $reason !== null => 'status',
            $latestCheck?->message_type === 'ai_evaluation' => 'success',
            $lastMessage !== null => 'status',
            default => null,
        };
        $lastMessageDatumId = match (true) {
            $hasUnresolvedAttemptFailure => is_numeric($workerLastFailureDatumId)
                ? (int) $workerLastFailureDatumId
                : null,
            $hasStoppedWorker,
            $hasOrphanedQueue => null,
            $failedPendingResources > 0 => null,
            $hasActualFailure && $latestCheck?->message_type === 'ai_failed' => (int) $latestCheck->datum_id,
            $waitingResources > 0 => null,
            $reason !== null => null,
            $latestCheck?->message_type === 'ai_evaluation' => (int) $latestCheck->datum_id,
            default => null,
        };

        return [
            'state' => $state,
            'checked_at' => $workerHeartbeatAt ?? $workerLastAttemptAt ?? $latestCheck?->created_at,
            'reason' => $reason,
            'last_message' => $lastMessage,
            'last_message_at' => $lastMessageAt,
            'last_message_type' => $lastMessageType,
            'last_message_datum_id' => $lastMessageDatumId,
            'pending_resources' => $pendingResources,
            'waiting_resources' => $waitingResources,
            'failed_pending_resources' => $failedPendingResources,
            'legacy_untracked_resources' => $legacyUntrackedResources,
            'worker_last_seen_at' => $workerLastSeenAt,
            'worker_heartbeat_at' => $workerHeartbeatAt,
            'worker_is_active' => $workerIsActive,
            'queue_jobs' => $queueJobs,
            'processing_jobs' => $processingJobs,
            'orphaned_resources' => $orphanedResources,
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
