<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateAcceptedDatumScore
{
    public function __construct(
        private ResolveAiManualPointMaximum $maximumResolver,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    public function handle(User $reviewer, Datum $datum, float $point, string $reason): Datum
    {
        $updatedDatum = DB::transaction(function () use ($reviewer, $datum, $point, $reason): Datum {
            $lockedDatum = Datum::query()
                ->with(['criterion.report', 'criterion.criterionEvaluations', 'criterion.formula', 'user'])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('updateAcceptedScore', $lockedDatum);

            $maximumPoint = $this->maximumResolver->handle($lockedDatum);
            if ($maximumPoint === null || ! is_finite($point) || $point < 0 || $point > $maximumPoint) {
                throw ValidationException::withMessages([
                    'point' => 'Kiritilgan ball foydalanuvchi uchun belgilangan chegaraga mos emas.',
                ]);
            }

            $previousPoint = (float) $lockedDatum->point;
            $point = round($point, 4);
            $auditMessage = 'Super administrator tasdiqlangan resurs ballini '
                .number_format($previousPoint, 4, '.', '').' dan '
                .number_format($point, 4, '.', '').' ga o‘zgartirdi. Sabab: '.trim($reason);

            $lockedDatum->update([
                'point' => $point,
                'reason' => $auditMessage,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'warning',
                'message' => $auditMessage,
                'message_type' => 'accepted_score_updated_by_super_admin',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($updatedDatum->criterion->report);

        return $updatedDatum->refresh();
    }
}
