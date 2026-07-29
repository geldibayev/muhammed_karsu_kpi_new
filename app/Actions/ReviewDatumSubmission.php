<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use App\Services\HIndexScoreCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewDatumSubmission
{
    public function __construct(
        private RecalculateReportPoints $recalculateReportPoints,
        private HIndexScoreCalculator $hIndexScoreCalculator,
    ) {}

    public function approve(
        User $reviewer,
        Datum $datum,
        ?int $scoreOptionId = null,
        ?float $reviewerPoint = null,
    ): Datum {
        $reviewedDatum = DB::transaction(function () use (
            $reviewer,
            $datum,
            $scoreOptionId,
            $reviewerPoint,
        ): Datum {
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

            if ($lockedDatum->criterion->isHIndexCriterion()) {
                $calculation = $this->hIndexScoreCalculator->calculate(
                    $lockedDatum->material['profiles'] ?? [],
                    max(0, (float) $evaluation->score),
                );
                $message = 'Mas’ul tomonidan tasdiqlandi. '.$calculation['summary'];

                $lockedDatum->update([
                    'status' => 'accepted',
                    'point' => $calculation['total'],
                    'reason' => $message,
                    'reviewer_hemis_id' => null,
                ]);
                $lockedDatum->histories()->create([
                    'user_id' => $reviewer->getKey(),
                    'type' => 'success',
                    'message' => $message,
                    'message_type' => 'h_index_review_approved',
                ]);

                return $lockedDatum;
            }

            ['point' => $point, 'rule' => $rule] = $this->approvedScore(
                $lockedDatum,
                $evaluation,
                $scoreOptionId,
                $reviewerPoint,
            );
            $message = 'Mas’ul tomonidan tasdiqlandi. Qoida: '.$rule
                .'. Avtomatik xom ball: '.number_format($point, 2, '.', '').'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'reason' => $message,
                'reviewer_hemis_id' => null,
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
        ?float $reviewerPoint,
    ): array {
        $maximumPoint = max(0, (float) $evaluation->score);

        if ($datum->criterion->checking === 'ai') {
            $submissionMaximum = $datum->criterion->formula_id === 3
                ? max(0, (float) config('kpi.ai_unlimited_submission_max_point', 1))
                : $maximumPoint;

            if ($reviewerPoint === null || $reviewerPoint < 0 || $reviewerPoint > $submissionMaximum) {
                throw ValidationException::withMessages([
                    'point' => "Ball 0 dan {$submissionMaximum} gacha bo‘lishi kerak.",
                ]);
            }

            return [
                'point' => $reviewerPoint,
                'rule' => 'mas’ul kiritgan ball',
            ];
        }

        if ($datum->criterion->checking !== 'manual') {
            return [
                'point' => $maximumPoint,
                'rule' => 'daraja bo‘yicha maksimal ball',
            ];
        }

        $optionQuery = CriterionManualScoreOption::query()
            ->where('criterion_id', $datum->criterion_id)
            ->where('active', true)
            ->lockForUpdate();

        if ($scoreOptionId !== null) {
            $optionQuery->whereKey($scoreOptionId);
        }

        $options = $optionQuery->limit(2)->get();
        $option = $options->count() === 1 ? $options->first() : null;

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
                'reviewer_hemis_id' => null,
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
