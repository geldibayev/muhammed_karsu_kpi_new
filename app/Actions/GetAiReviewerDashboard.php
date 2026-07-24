<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GetAiReviewerDashboard
{
    /**
     * @return array{
     *     status: array{state: 'operational'|'unavailable'|'unknown', checked_at: CarbonInterface|null},
     *     statistics: array{
     *         total_checks: int,
     *         successful_checks: int,
     *         failed_checks: int,
     *         last_success_at: CarbonInterface|null,
     *         last_failure_at: CarbonInterface|null
     *     },
     *     submissionStatistics: array{
     *         total: int,
     *         received: int,
     *         checking: int,
     *         accepted: int,
     *         cancelled: int,
     *         pending: int,
     *         resolved: int,
     *         approval_rate: float,
     *         last_submission_at: CarbonInterface|null
     *     },
     *     recentChecks: Collection<int, DatumHistory>
     * }
     */
    public function handle(): array
    {
        $aggregate = DatumHistory::query()
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
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed'])
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get();

        $latestCheck = $recentChecks->first();
        $submissionAggregate = Datum::query()
            ->toBase()
            ->whereIn('status', ['received', 'checking', 'accepted', 'cancelled'])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS received")
            ->selectRaw("SUM(CASE WHEN status = 'checking' THEN 1 ELSE 0 END) AS checking")
            ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->selectRaw('MAX(created_at) AS last_submission_at')
            ->first();

        $received = (int) ($submissionAggregate->received ?? 0);
        $checking = (int) ($submissionAggregate->checking ?? 0);
        $accepted = (int) ($submissionAggregate->accepted ?? 0);
        $cancelled = (int) ($submissionAggregate->cancelled ?? 0);
        $resolved = $accepted + $cancelled;

        return [
            'status' => [
                'state' => match ($latestCheck?->message_type) {
                    'ai_evaluation' => 'operational',
                    'ai_failed' => 'unavailable',
                    default => 'unknown',
                },
                'checked_at' => $latestCheck?->created_at,
            ],
            'statistics' => [
                'total_checks' => (int) $aggregate->total_checks,
                'successful_checks' => (int) ($aggregate->successful_checks ?? 0),
                'failed_checks' => (int) ($aggregate->failed_checks ?? 0),
                'last_success_at' => $this->toDate($aggregate->last_success_at),
                'last_failure_at' => $this->toDate($aggregate->last_failure_at),
            ],
            'submissionStatistics' => [
                'total' => (int) ($submissionAggregate->total ?? 0),
                'received' => $received,
                'checking' => $checking,
                'accepted' => $accepted,
                'cancelled' => $cancelled,
                'pending' => $received + $checking,
                'resolved' => $resolved,
                'approval_rate' => $resolved > 0
                    ? round(($accepted / $resolved) * 100, 1)
                    : 0.0,
                'last_submission_at' => $this->toDate($submissionAggregate->last_submission_at ?? null),
            ],
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
