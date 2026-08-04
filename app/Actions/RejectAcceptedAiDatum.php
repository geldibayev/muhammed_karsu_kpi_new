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

            Gate::forUser($reviewer)->authorize('overrideAiAcceptance', $lockedDatum);

            $auditMessage = 'Gemini tasdiqlagan resurs inson tekshiruvida xato deb topildi va rad etildi. Sabab: '.trim($reason);

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
                'message_type' => 'human_override_ai_rejected',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($rejectedDatum->criterion->report);

        return $rejectedDatum->refresh();
    }
}
