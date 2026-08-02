<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use App\Services\HIndexScoreCalculator;
use App\Services\OakArticleScoreCalculator;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewDatumSubmission
{
    public function __construct(
        private RecalculateReportPoints $recalculateReportPoints,
        private HIndexScoreCalculator $hIndexScoreCalculator,
        private OakArticleScoreCalculator $oakArticleScoreCalculator,
        private PrintedEducationalLiteratureScoreCalculator $printedLiteratureScoreCalculator,
    ) {}

    public function approve(
        User $reviewer,
        Datum $datum,
        ?int $scoreOptionId = null,
        ?float $reviewerPoint = null,
        ?int $authorCount = null,
        ?int $pageCount = null,
    ): Datum {
        $reviewedDatum = DB::transaction(function () use (
            $reviewer,
            $datum,
            $scoreOptionId,
            $reviewerPoint,
            $authorCount,
            $pageCount,
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
                $authorCount,
                $pageCount,
            );
            $message = 'Mas’ul tomonidan tasdiqlandi. Qoida: '.$rule
                .'. Hisoblangan ball: '.number_format(
                    $point,
                    ($lockedDatum->criterion->isOakArticleCriterion()
                        || $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion()) ? 4 : 2,
                    '.',
                    '',
                ).'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'author_count' => ($lockedDatum->criterion->isOakArticleCriterion()
                    || $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion()) ? $authorCount : null,
                'page_count' => $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion() ? $pageCount : null,
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
        ?int $authorCount,
        ?int $pageCount,
    ): array {
        $maximumPoint = max(0, (float) $evaluation->score);

        if ($datum->criterion->checking === 'ai') {
            if ($datum->criterion->isOakArticleCriterion()) {
                if ($authorCount === null || $authorCount < 1 || $authorCount > 1000) {
                    throw ValidationException::withMessages([
                        'author_count' => 'Mualliflar soni 1 dan 1000 gacha bo‘lishi kerak.',
                    ]);
                }

                $basePoint = $this->oakArticleScoreCalculator->basePoint($datum->user->degree);

                return [
                    'point' => $this->oakArticleScoreCalculator->calculate($datum->user->degree, $authorCount),
                    'rule' => number_format($basePoint, 2, '.', '').' ball / '.$authorCount.' muallif',
                ];
            }

            if ($datum->criterion->isPrintedEducationalLiteratureCriterion()) {
                if ($pageCount === null || $pageCount < 1 || $pageCount > 100000) {
                    throw ValidationException::withMessages([
                        'page_count' => 'Sahifalar soni 1 dan 100000 gacha bo\'lishi kerak.',
                    ]);
                }

                if ($authorCount === null || $authorCount < 1 || $authorCount > 1000) {
                    throw ValidationException::withMessages([
                        'author_count' => 'Mualliflar soni 1 dan 1000 gacha bo\'lishi kerak.',
                    ]);
                }

                $rate = $datum->criterion->code === '1.2' ? 0.4 : 0.3;

                return [
                    'point' => $this->printedLiteratureScoreCalculator->calculate(
                        (string) $datum->criterion->code,
                        $pageCount,
                        $authorCount,
                    ),
                    'rule' => $pageCount.' sahifa / 16 × '.number_format($rate, 1, '.', '')
                        .' / '.$authorCount.' muallif',
                ];
            }

            $submissionMaximum = $datum->criterion->aiSubmissionMaximum($maximumPoint);

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
            throw ValidationException::withMessages([
                'datum' => "{$datum->criterion->checking} tekshirish rejimi uchun alohida baholash mexanizmi sozlanmagan.",
            ]);
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
