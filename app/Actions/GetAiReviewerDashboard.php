<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumHistory;
use Illuminate\Database\Query\Builder;

class GetAiReviewerDashboard
{
    /**
     * @return array{
     *     status: array<string, mixed>,
     *     resourceStatistics: array{
     *         total: int,
     *         evaluated: int,
     *         accepted: int,
     *         cancelled: int,
     *         human_review: int,
     *         waiting: int,
     *         failed_pending: int,
     *         legacy_untracked: int,
     *         evaluation_rate: float
     *     }
     * }
     */
    public function __construct(private GetAiReviewerHealth $getAiReviewerHealth) {}

    public function handle(): array
    {
        $aiHistorySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed', 'submission_created', 'ai_queued'])
            ->groupBy('datum_id');

        $resourceAggregate = Datum::query()
            ->toBase()
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->leftJoinSub($aiHistorySummary->toBase(), 'ai_history', function (Builder $join): void {
                $join->on('ai_history.datum_id', '=', 'data.id');
            })
            ->where('criteria.checking', 'ai')
            ->where('data.status', '!=', 'deleted')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN data.status IN ('accepted', 'cancelled') OR (data.status IN ('received', 'checking') AND COALESCE(ai_history.last_evaluation_id, 0) > COALESCE(ai_history.last_queue_id, 0) AND COALESCE(ai_history.last_evaluation_id, 0) > COALESCE(ai_history.last_failure_id, 0)) THEN 1 ELSE 0 END) AS evaluated")
            ->selectRaw("SUM(CASE WHEN data.status = 'accepted' THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN data.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.last_evaluation_id, 0) > COALESCE(ai_history.last_queue_id, 0) AND COALESCE(ai_history.last_evaluation_id, 0) > COALESCE(ai_history.last_failure_id, 0) THEN 1 ELSE 0 END) AS human_review")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_evaluation_id, 0) AND COALESCE(ai_history.last_queue_id, 0) > COALESCE(ai_history.last_failure_id, 0) THEN 1 ELSE 0 END) AS waiting")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.last_failure_id, 0) > COALESCE(ai_history.last_evaluation_id, 0) AND COALESCE(ai_history.last_failure_id, 0) > COALESCE(ai_history.last_queue_id, 0) THEN 1 ELSE 0 END) AS failed_pending")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.last_evaluation_id, 0) = 0 AND COALESCE(ai_history.last_failure_id, 0) = 0 AND COALESCE(ai_history.last_queue_id, 0) = 0 THEN 1 ELSE 0 END) AS legacy_untracked")
            ->first();

        $totalResources = (int) ($resourceAggregate->total ?? 0);
        $evaluatedResources = (int) ($resourceAggregate->evaluated ?? 0);
        $evaluationRate = $totalResources > 0
            ? round(($evaluatedResources / $totalResources) * 100, 1)
            : 0.0;

        if ($evaluatedResources < $totalResources) {
            $evaluationRate = min(99.9, $evaluationRate);
        }

        return [
            'status' => $this->getAiReviewerHealth->handle(),
            'resourceStatistics' => [
                'total' => $totalResources,
                'evaluated' => $evaluatedResources,
                'accepted' => (int) ($resourceAggregate->accepted ?? 0),
                'cancelled' => (int) ($resourceAggregate->cancelled ?? 0),
                'human_review' => (int) ($resourceAggregate->human_review ?? 0),
                'waiting' => (int) ($resourceAggregate->waiting ?? 0),
                'failed_pending' => (int) ($resourceAggregate->failed_pending ?? 0),
                'legacy_untracked' => (int) ($resourceAggregate->legacy_untracked ?? 0),
                'evaluation_rate' => $evaluationRate,
            ],
        ];
    }
}
