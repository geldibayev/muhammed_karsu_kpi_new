<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use App\Services\HIndexScoreCalculator;
use App\Services\IndustryFundingScoreCalculator;
use App\Services\OakArticleScoreCalculator;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use App\Support\InternationalCooperationCriterionRule;
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
        private ScientificPublicationHumanReviewScoreCalculator $scientificPublicationScoreCalculator,
        private IndustryFundingScoreCalculator $industryFundingScoreCalculator,
    ) {}

    public function approve(
        User $reviewer,
        Datum $datum,
        ?int $scoreOptionId = null,
        ?float $reviewerPoint = null,
        ?int $authorCount = null,
        ?int $pageCount = null,
        ?int $impactFactor = null,
        ?string $publicationTier = null,
        ?string $universityTier = null,
        ?float $receivedAmount = null,
    ): Datum {
        $reviewedDatum = DB::transaction(function () use (
            $reviewer,
            $datum,
            $scoreOptionId,
            $reviewerPoint,
            $authorCount,
            $pageCount,
            $impactFactor,
            $publicationTier,
            $universityTier,
            $receivedAmount,
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
                $impactFactor,
                $publicationTier,
                $universityTier,
                $receivedAmount,
            );
            $message = 'Mas’ul tomonidan tasdiqlandi. Qoida: '.$rule
                .'. Hisoblangan ball: '.number_format(
                    $point,
                    ($lockedDatum->criterion->isOakArticleCriterion()
                        || $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion()
                        || $lockedDatum->criterion->isIndustryFundingCriterion()
                        || $lockedDatum->criterion->usesAuthorDividedAiHumanReviewScore()) ? 4 : 2,
                    '.',
                    '',
                ).'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'author_count' => ($lockedDatum->criterion->isOakArticleCriterion()
                    || $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion()
                    || $lockedDatum->criterion->isIndustryFundingCriterion()
                    || $lockedDatum->criterion->usesAuthorDividedAiHumanReviewScore()) ? $authorCount : null,
                'page_count' => $lockedDatum->criterion->isPrintedEducationalLiteratureCriterion() ? $pageCount : null,
                'impact_factor' => $lockedDatum->criterion->usesImpactFactorAiHumanReviewScore()
                    ? $impactFactor
                    : null,
                'publication_tier' => $lockedDatum->criterion->usesPublicationTierAiHumanReviewScore()
                    ? $publicationTier
                    : null,
                'university_tier' => $lockedDatum->criterion->isInternationalCooperationCriterion()
                    ? $universityTier
                    : null,
                'received_amount' => $lockedDatum->criterion->isIndustryFundingCriterion()
                    ? $receivedAmount
                    : null,
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
        ?int $impactFactor,
        ?string $publicationTier,
        ?string $universityTier,
        ?float $receivedAmount,
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

            if ($datum->criterion->usesAutomaticAiHumanReviewScore()) {
                return [
                    'point' => $maximumPoint,
                    'rule' => 'foydalanuvchining baholash toifasi bo‘yicha avtomatik ball',
                ];
            }

            if ($datum->criterion->usesImpactFactorAiHumanReviewScore()) {
                if ($impactFactor === null || $impactFactor < 1 || $impactFactor > 1000) {
                    throw ValidationException::withMessages([
                        'impact_factor' => 'Impakt faktor 1 dan 1000 gacha bo‘lgan butun son bo‘lishi kerak.',
                    ]);
                }

                $point = $this->scientificPublicationScoreCalculator->impactFactorPoint(
                    $maximumPoint,
                    $impactFactor,
                );
                $percentage = min($impactFactor, 10) * 10;

                return [
                    'point' => $point,
                    'rule' => $impactFactor.' impakt faktor — '.$percentage.'% × '
                        .number_format($maximumPoint, 2, '.', '').' maksimal ball',
                ];
            }

            if ($datum->criterion->usesPublicationTierAiHumanReviewScore()) {
                if ($publicationTier === null
                    || ! array_key_exists(
                        $publicationTier,
                        ScientificPublicationHumanReviewScoreCalculator::PUBLICATION_TIER_POINTS,
                    )) {
                    throw ValidationException::withMessages([
                        'publication_tier' => 'Jurnal kvartili yoki nashr turini tanlang.',
                    ]);
                }

                return [
                    'point' => $this->scientificPublicationScoreCalculator
                        ->publicationTierPoint($publicationTier),
                    'rule' => match ($publicationTier) {
                        'q1', 'q2', 'q3', 'q4' => mb_strtoupper($publicationTier).' jurnal kvartili',
                        'conference' => 'konferensiya maqolasi',
                    },
                ];
            }

            if ($datum->criterion->isInternationalCooperationCriterion()) {
                $point = $universityTier === null
                    ? null
                    : InternationalCooperationCriterionRule::pointForUniversityTier(
                        $maximumPoint,
                        $universityTier,
                    );

                if ($point === null) {
                    throw ValidationException::withMessages([
                        'university_tier' => 'Universitetning xalqaro reytingdagi Top darajasini tanlang.',
                    ]);
                }

                return [
                    'point' => $point,
                    'rule' => match ($universityTier) {
                        'top_100' => 'Universitet Top-100 reytingida',
                        'top_300' => 'Universitet Top-101–300 reytingida',
                        'top_500' => 'Universitet Top-301–500 reytingida',
                        'top_1000' => 'Universitet Top-501–1000 reytingida',
                    },
                ];
            }

            if ($datum->criterion->isIndustryFundingCriterion()) {
                if ($receivedAmount === null || $receivedAmount <= 0) {
                    throw ValidationException::withMessages([
                        'received_amount' => 'Universitet hisobiga tushgan summa musbat bo‘lishi kerak.',
                    ]);
                }

                if ($authorCount === null || $authorCount < 1 || $authorCount > 1000) {
                    throw ValidationException::withMessages([
                        'author_count' => 'Hammualliflar soni 1 dan 1000 gacha bo‘lishi kerak.',
                    ]);
                }

                return [
                    'point' => $this->industryFundingScoreCalculator->calculate(
                        $receivedAmount,
                        $authorCount,
                    ),
                    'rule' => number_format($receivedAmount, 2, '.', '')
                        .' so‘m / 1 000 000 / '.$authorCount.' hammuallif',
                ];
            }

            if ($datum->criterion->usesAuthorDividedAiHumanReviewScore()) {
                if ($authorCount === null || $authorCount < 1 || $authorCount > 1000) {
                    throw ValidationException::withMessages([
                        'author_count' => 'Mualliflar soni 1 dan 1000 gacha bo‘lishi kerak.',
                    ]);
                }

                return [
                    'point' => $this->scientificPublicationScoreCalculator
                        ->authorDividedPoint($maximumPoint, $authorCount),
                    'rule' => number_format($maximumPoint, 2, '.', '')
                        .' bazaviy ball / '.$authorCount.' muallif',
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
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
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
