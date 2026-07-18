<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewDatumSubmission
{
    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    public function approve(User $reviewer, Datum $datum, ?int $scoreOptionId = null): Datum
    {
        $reviewedDatum = DB::transaction(function () use ($reviewer, $datum, $scoreOptionId): Datum {
            $lockedDatum = Datum::query()
                ->with(['criterion.report', 'user'])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('review', $lockedDatum);

            $evaluation = CriterionEvaluation::query()
                ->where('criterion_id', $lockedDatum->criterion_id)
                ->where('evaluation', $lockedDatum->user->degree)
                ->where('has', '1')
                ->first();

            if ($evaluation === null) {
                throw ValidationException::withMessages([
                    'datum' => 'Foydalanuvchi darajasi uchun avtomatik ball sozlanmagan.',
                ]);
            }

            ['point' => $point, 'rule' => $rule] = $this->approvedScore(
                $lockedDatum,
                $evaluation,
                $scoreOptionId,
            );
            $message = 'Mas’ul tomonidan tasdiqlandi. Qoida: '.$rule
                .'. Avtomatik xom ball: '.number_format($point, 2, '.', '').'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'reason' => $message,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'success',
                'message' => $message,
                'message_type' => 'manual_review_approved',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($reviewedDatum->criterion->report);

        return $reviewedDatum->refresh();
    }

    /** @return array{point: float, rule: string} */
    private function approvedScore(
        Datum $datum,
        CriterionEvaluation $evaluation,
        ?int $scoreOptionId,
    ): array {
        $maximumPoint = max(0, (float) $evaluation->score);

        if ($datum->criterion->checking !== 'manual') {
            return [
                'point' => $maximumPoint,
                'rule' => 'daraja bo‘yicha maksimal ball',
            ];
        }

        $option = CriterionManualScoreOption::query()
            ->whereKey($scoreOptionId)
            ->where('criterion_id', $datum->criterion_id)
            ->where('active', true)
            ->lockForUpdate()
            ->first();

        if ($option === null) {
            throw ValidationException::withMessages([
                'score_option_id' => 'Ushbu mezon uchun baholash variantini tanlang.',
            ]);
        }

        $point = max(0, (float) $option->point);

        if ($point > $maximumPoint) {
            throw ValidationException::withMessages([
                'score_option_id' => 'Tanlangan ball foydalanuvchi uchun belgilangan maksimal balldan oshadi.',
            ]);
        }

        return [
            'point' => $point,
            'rule' => (string) data_get($option->label, 'uz', $option->code),
        ];
    }

    public function reject(User $reviewer, Datum $datum, string $reason): Datum
    {
        $reviewedDatum = DB::transaction(function () use ($reviewer, $datum, $reason): Datum {
            $lockedDatum = Datum::query()
                ->with('criterion.report')
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('review', $lockedDatum);

            $reason = trim($reason);
            $lockedDatum->update([
                'status' => 'cancelled',
                'point' => 0,
                'reason' => $reason,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'error',
                'message' => $reason,
                'message_type' => 'manual_review_rejected',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($reviewedDatum->criterion->report);

        return $reviewedDatum->refresh();
    }
}
