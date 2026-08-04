<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\User;
use App\Services\DatumResourceFingerprintGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveCancelledAiDatum
{
    public function __construct(
        private ResolveAiManualPointMaximum $maximumResolver,
        private DatumResourceFingerprintGenerator $fingerprintGenerator,
        private DatumResourceIdentifierRegistry $identifierRegistry,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    public function handle(User $reviewer, Datum $datum, float $point): Datum
    {
        $approvedDatum = DB::transaction(function () use ($reviewer, $datum, $point): Datum {
            $lockedDatum = Datum::query()
                ->with([
                    'criterion.report',
                    'criterion.criterionEvaluations',
                    'criterion.formula',
                    'user',
                ])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('overrideCancellation', $lockedDatum);

            $maximumPoint = $this->maximumResolver->handle($lockedDatum);

            if ($maximumPoint === null
                || ! is_finite($point)
                || $point < 0
                || $point > $maximumPoint) {
                throw ValidationException::withMessages([
                    'point' => 'Kiritilgan ball foydalanuvchi uchun belgilangan chegaraga mos emas.',
                ]);
            }

            $point = round($point, 4);
            $aiDecision = $this->latestDecisionWasAi($lockedDatum);
            $auditMessage = ($aiDecision
                ? 'Gemini rad etgan resurs'
                : 'Oldin rad etilgan resurs')
                .' inson tekshiruvida tasdiqlandi. '
                .'Qo‘lda kiritilgan ball: '.number_format($point, 4, '.', '').'. '
                .'Maksimal ruxsat etilgan ball: '.number_format($maximumPoint, 4, '.', '').'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'reviewer_hemis_id' => null,
                'reason' => $auditMessage,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'success',
                'message' => $auditMessage,
                'message_type' => $aiDecision
                    ? 'human_override_ai_approved'
                    : 'human_override_approved',
            ]);
            $this->identifierRegistry->register(
                $lockedDatum,
                $lockedDatum->criterion->report_id,
                $this->fingerprintGenerator->forDatum($lockedDatum),
            );

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($approvedDatum->criterion->report);

        return $approvedDatum->refresh();
    }

    private function latestDecisionWasAi(Datum $datum): bool
    {
        $lastAiCancellationId = (int) $datum->histories()
            ->where('message_type', 'ai_evaluation')
            ->where('type', 'error')
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

        return $lastAiCancellationId > $lastHumanDecisionId;
    }
}
