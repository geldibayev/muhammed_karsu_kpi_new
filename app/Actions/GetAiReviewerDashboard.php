<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class GetAiReviewerDashboard
{
    /**
     * @return array{
     *     status: array{
     *         state: 'operational'|'processing'|'degraded'|'unavailable'|'unknown',
     *         checked_at: CarbonInterface|null,
     *         reason: string|null,
     *         pending_resources: int,
     *         waiting_resources: int,
     *         failed_pending_resources: int,
     *         oldest_waiting_at: CarbonInterface|null
     *     },
     *     statistics: array{
     *         total_checks: int,
     *         successful_checks: int,
     *         failed_checks: int,
     *         last_success_at: CarbonInterface|null,
     *         last_failure_at: CarbonInterface|null
     *     },
     *     resourceStatistics: array{
     *         total: int,
     *         evaluated: int,
     *         waiting: int,
     *         failed_pending: int,
     *         accepted: int,
     *         cancelled: int,
     *         human_review: int,
     *         evaluation_rate: float,
     *         last_submission_at: CarbonInterface|null
     *     },
     *     reportStatistics: Collection<int, array{
     *         id: int,
     *         name: string,
     *         active: bool,
     *         total: int,
     *         evaluated: int,
     *         waiting: int,
     *         failed_pending: int,
     *         accepted: int,
     *         cancelled: int,
     *         evaluation_rate: float
     *     }>,
     *     recentChecks: Collection<int, DatumHistory>
     * }
     */
    public function __construct(private GetAiReviewerHealth $getAiReviewerHealth) {}

    public function handle(): array
    {
        $status = $this->getAiReviewerHealth->handle();
        $aiDatumIds = Datum::query()
            ->select('data.id')
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->where('criteria.checking', 'ai');

        $aggregate = DatumHistory::query()
            ->whereIn('datum_id', clone $aiDatumIds)
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->selectRaw('COUNT(*) AS total_checks')
            ->selectRaw("SUM(CASE WHEN message_type = 'ai_evaluation' THEN 1 ELSE 0 END) AS successful_checks")
            ->selectRaw("SUM(CASE WHEN message_type = 'ai_failed' THEN 1 ELSE 0 END) AS failed_checks")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN created_at END) AS last_success_at")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN created_at END) AS last_failure_at")
            ->firstOrFail();

        $recentChecks = DatumHistory::query()
            ->select(['id', 'datum_id', 'type', 'message', 'message_type', 'created_at'])
            ->with([
                'datum:id,user_id,criterion_id,status,point',
                'datum.user:id,name,hemis_id',
                'datum.criterion:id,name',
            ])
            ->whereIn('datum_id', clone $aiDatumIds)
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get();

        $aiHistorySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN 1 ELSE 0 END) AS has_evaluation")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN 1 ELSE 0 END) AS has_failure")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->groupBy('datum_id');

        $resourceAggregate = Datum::query()
            ->toBase()
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->leftJoinSub((clone $aiHistorySummary)->toBase(), 'ai_history', function (Builder $join): void {
                $join->on('ai_history.datum_id', '=', 'data.id');
            })
            ->where('criteria.checking', 'ai')
            ->where('data.status', '!=', 'deleted')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN data.status IN ('accepted', 'cancelled') OR COALESCE(ai_history.has_evaluation, 0) = 1 THEN 1 ELSE 0 END) AS evaluated")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 0 THEN 1 ELSE 0 END) AS waiting")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 1 THEN 1 ELSE 0 END) AS failed_pending")
            ->selectRaw("SUM(CASE WHEN data.status = 'accepted' THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN data.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->selectRaw("SUM(CASE WHEN data.status = 'checking' AND COALESCE(ai_history.has_evaluation, 0) = 1 THEN 1 ELSE 0 END) AS human_review")
            ->selectRaw('MAX(data.created_at) AS last_submission_at')
            ->first();

        $totalResources = (int) ($resourceAggregate->total ?? 0);
        $evaluatedResources = (int) ($resourceAggregate->evaluated ?? 0);
        $reportStatistics = Datum::query()
            ->toBase()
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->join('reports', 'reports.id', '=', 'criteria.report_id')
            ->leftJoinSub((clone $aiHistorySummary)->toBase(), 'ai_history', function (Builder $join): void {
                $join->on('ai_history.datum_id', '=', 'data.id');
            })
            ->where('criteria.checking', 'ai')
            ->where('data.status', '!=', 'deleted')
            ->groupBy(['reports.id', 'reports.name', 'reports.status'])
            ->select([
                'reports.id',
                'reports.name',
                'reports.status AS report_status',
            ])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN data.status IN ('accepted', 'cancelled') OR COALESCE(ai_history.has_evaluation, 0) = 1 THEN 1 ELSE 0 END) AS evaluated")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 0 THEN 1 ELSE 0 END) AS waiting")
            ->selectRaw("SUM(CASE WHEN data.status IN ('received', 'checking') AND COALESCE(ai_history.has_evaluation, 0) = 0 AND COALESCE(ai_history.has_failure, 0) = 1 THEN 1 ELSE 0 END) AS failed_pending")
            ->selectRaw("SUM(CASE WHEN data.status = 'accepted' THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN data.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->orderByDesc('reports.status')
            ->orderByDesc('reports.id')
            ->get()
            ->map(function (object $report): array {
                $name = json_decode((string) $report->name, true);
                $total = (int) $report->total;
                $evaluated = (int) $report->evaluated;

                return [
                    'id' => (int) $report->id,
                    'name' => is_array($name)
                        ? (string) data_get($name, 'uz', 'Nomsiz hisobot')
                        : 'Nomsiz hisobot',
                    'active' => (string) $report->report_status === '1',
                    'total' => $total,
                    'evaluated' => $evaluated,
                    'waiting' => (int) $report->waiting,
                    'failed_pending' => (int) $report->failed_pending,
                    'accepted' => (int) $report->accepted,
                    'cancelled' => (int) $report->cancelled,
                    'evaluation_rate' => $total > 0
                        ? round(($evaluated / $total) * 100, 1)
                        : 0.0,
                ];
            })
            ->values();

        return [
            'status' => $status,
            'statistics' => [
                'total_checks' => (int) $aggregate->total_checks,
                'successful_checks' => (int) ($aggregate->successful_checks ?? 0),
                'failed_checks' => (int) ($aggregate->failed_checks ?? 0),
                'last_success_at' => $this->toDate($aggregate->last_success_at),
                'last_failure_at' => $this->toDate($aggregate->last_failure_at),
            ],
            'resourceStatistics' => [
                'total' => $totalResources,
                'evaluated' => $evaluatedResources,
                'waiting' => (int) ($resourceAggregate->waiting ?? 0),
                'failed_pending' => (int) ($resourceAggregate->failed_pending ?? 0),
                'accepted' => (int) ($resourceAggregate->accepted ?? 0),
                'cancelled' => (int) ($resourceAggregate->cancelled ?? 0),
                'human_review' => (int) ($resourceAggregate->human_review ?? 0),
                'evaluation_rate' => $totalResources > 0
                    ? round(($evaluatedResources / $totalResources) * 100, 1)
                    : 0.0,
                'last_submission_at' => $this->toDate($resourceAggregate->last_submission_at ?? null),
            ],
            'reportStatistics' => $reportStatistics,
            'recentChecks' => $recentChecks,
        ];
    }

    private function toDate(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }
}
