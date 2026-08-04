<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RejectAcceptedAiDatum
{
    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    public function handle(User $reviewer, Datum $datum, string $reason): Datum
    {
        $rejectedDatum = DB::transaction(function () use ($reviewer, $datum, $reason): Datum {
            $lockedDatum = Datum::query()
                ->with('criterion.report')
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('overrideAcceptance', $lockedDatum);

            $aiDecision = $this->latestDecisionWasAi($lockedDatum);
            $auditMessage = ($aiDecision
                ? 'Gemini tasdiqlagan resurs'
                : 'Oldin tasdiqlangan resurs')
                .' inson tekshiruvida xato deb topildi va rad etildi. Sabab: '.trim($reason);

            $lockedDatum->update([
                'status' => 'cancelled',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
                'reviewer_hemis_id' => null,
                'reason' => $auditMessage,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'error',
                'message' => $auditMessage,
                'message_type' => $aiDecision
                    ? 'human_override_ai_rejected'
                    : 'human_override_rejected',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($rejectedDatum->criterion->report);

        return $rejectedDatum->refresh();
    }

    private function latestDecisionWasAi(Datum $datum): bool
    {
        $lastAiAcceptanceId = (int) $datum->histories()
            ->where('message_type', 'ai_evaluation')
            ->where('type', 'success')
            ->max('id');
        $lastHumanDecisionId = (int) $datum->histories()
            ->whereIn('message_type', [
                'manual_review_approved',
                'manual_review_rejected',
                'h_index_review_approved',
                'human_override_ai_rejected',
                'human_override_ai_approved',
                'human_override_rejected',
                'human_override_approved',
                'criterion_transferred',
            ])
            ->max('id');

        return $lastAiAcceptanceId > $lastHumanDecisionId;
    }
}
