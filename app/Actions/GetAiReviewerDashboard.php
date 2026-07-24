<?php

namespace App\Actions;

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
