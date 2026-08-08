<?php

namespace App\Actions;

use App\Models\DatumHistory;
use App\Models\User;
use Carbon\CarbonImmutable;

class GetAiHumanReviewerStatistics
{
    /**
     * @return array{
     *     summary: array{reviewers: int, total: int, checked: int, unchecked: int, approved: int, rejected: int, completion_rate: float},
     *     reviewers: list<array{hemis_id: int, name: string, total: int, checked: int, unchecked: int, approved: int, rejected: int, completion_rate: float, last_assigned_at: CarbonImmutable}>
     * }
     */
    public function handle(): array
    {
        $latestAssignmentIds = DatumHistory::query()
            ->selectRaw('MAX(id)')
            ->where('message_type', 'ai_human_review_assigned')
            ->groupBy('datum_id');
        $reviewHistory = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type IN ('manual_review_approved', 'h_index_review_approved') THEN id ELSE 0 END) AS last_approved_id")
            ->selectRaw("MAX(CASE WHEN message_type = 'manual_review_rejected' THEN id ELSE 0 END) AS last_rejected_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('ai_queued', 'criterion_transferred', 'ai_human_review_unassigned') THEN id ELSE 0 END) AS last_invalidating_id")
            ->whereIn('message_type', [
                'manual_review_approved',
                'h_index_review_approved',
                'manual_review_rejected',
                'ai_queued',
                'criterion_transferred',
                'ai_human_review_unassigned',
            ])
            ->groupBy('datum_id');

        $assignments = DatumHistory::query()
            ->toBase()
            ->select([
                'datum_histories.id',
                'datum_histories.message',
                'datum_histories.created_at',
                'data.status',
                'data.reviewer_hemis_id',
                'review_history.last_approved_id',
                'review_history.last_rejected_id',
                'review_history.last_invalidating_id',
            ])
            ->join('data', 'data.id', '=', 'datum_histories.datum_id')
            ->leftJoinSub($reviewHistory->toBase(), 'review_history', function ($join): void {
                $join->on('review_history.datum_id', '=', 'datum_histories.datum_id');
            })
            ->whereIn('datum_histories.id', $latestAssignmentIds)
            ->get();

        $statisticsByHemisId = [];

        foreach ($assignments as $assignment) {
            $hemisId = $this->reviewerHemisId($assignment->message);

            if ($hemisId === null) {
                continue;
            }

            $assignmentId = (int) $assignment->id;
            $lastApprovedId = (int) ($assignment->last_approved_id ?? 0);
            $lastRejectedId = (int) ($assignment->last_rejected_id ?? 0);
            $lastDecisionId = max($lastApprovedId, $lastRejectedId);
            $lastInvalidatingId = (int) ($assignment->last_invalidating_id ?? 0);

            if ($lastInvalidatingId > $assignmentId && $lastInvalidatingId > $lastDecisionId) {
                continue;
            }

            $isApproved = $lastApprovedId > $assignmentId && $lastApprovedId > $lastRejectedId;
            $isRejected = $lastRejectedId > $assignmentId && $lastRejectedId > $lastApprovedId;
            $isUnchecked = $assignment->status === 'checking'
                && (int) $assignment->reviewer_hemis_id === $hemisId
                && ! $isApproved
                && ! $isRejected;

            if (! $isApproved && ! $isRejected && ! $isUnchecked) {
                continue;
            }

            $statisticsByHemisId[$hemisId] ??= [
                'hemis_id' => $hemisId,
                'total' => 0,
                'checked' => 0,
                'unchecked' => 0,
                'approved' => 0,
                'rejected' => 0,
                'last_assigned_at' => CarbonImmutable::parse($assignment->created_at),
            ];
            $statisticsByHemisId[$hemisId]['total']++;
            $assignedAt = CarbonImmutable::parse($assignment->created_at);

            if ($assignedAt->gt($statisticsByHemisId[$hemisId]['last_assigned_at'])) {
                $statisticsByHemisId[$hemisId]['last_assigned_at'] = $assignedAt;
            }

            if ($isApproved) {
                $statisticsByHemisId[$hemisId]['checked']++;
                $statisticsByHemisId[$hemisId]['approved']++;
            } elseif ($isRejected) {
                $statisticsByHemisId[$hemisId]['checked']++;
                $statisticsByHemisId[$hemisId]['rejected']++;
            } else {
                $statisticsByHemisId[$hemisId]['unchecked']++;
            }
        }

        $users = User::query()
            ->select(['id', 'hemis_id', 'name'])
            ->whereIn('hemis_id', array_keys($statisticsByHemisId))
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->hemis_id);

        $reviewers = collect($statisticsByHemisId)
            ->map(function (array $statistics) use ($users): array {
                $user = $users->get((string) $statistics['hemis_id']);

                return [
                    ...$statistics,
                    'name' => $user?->full ?: ($user?->short ?: 'Foydalanuvchi topilmadi'),
                    'completion_rate' => $statistics['total'] > 0
                        ? round(($statistics['checked'] / $statistics['total']) * 100, 1)
                        : 0.0,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return $right['unchecked'] <=> $left['unchecked']
                    ?: $right['total'] <=> $left['total']
                    ?: $left['name'] <=> $right['name'];
            })
            ->values()
            ->all();

        $summary = [
            'reviewers' => count($reviewers),
            'total' => array_sum(array_column($reviewers, 'total')),
            'checked' => array_sum(array_column($reviewers, 'checked')),
            'unchecked' => array_sum(array_column($reviewers, 'unchecked')),
            'approved' => array_sum(array_column($reviewers, 'approved')),
            'rejected' => array_sum(array_column($reviewers, 'rejected')),
            'completion_rate' => 0.0,
        ];
        $summary['completion_rate'] = $summary['total'] > 0
            ? round(($summary['checked'] / $summary['total']) * 100, 1)
            : 0.0;

        return compact('summary', 'reviewers');
    }

    private function reviewerHemisId(mixed $message): ?int
    {
        if (! is_string($message)
            || preg_match('/HEMIS ID\s+(\d+)/u', $message, $matches) !== 1) {
            return null;
        }

        $hemisId = (int) $matches[1];

        return $hemisId > 0 ? $hemisId : null;
    }
}
