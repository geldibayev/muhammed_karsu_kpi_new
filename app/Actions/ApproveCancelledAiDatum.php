<?php

namespace App\Actions;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use App\Services\DatumResourceFingerprintGenerator;
use App\Services\OakArticleScoreCalculator;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use App\Support\EducationalContentCriterionRule;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\ForeignLanguageCertificateCriterionRule;
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
        private ScientificPublicationHumanReviewScoreCalculator $scientificPublicationScoreCalculator,
        private OakArticleScoreCalculator $oakArticleScoreCalculator,
    ) {}

    public function handle(
        User $reviewer,
        Datum $datum,
        ?float $point,
        ?int $scoreOptionId = null,
        ?string $publicationTier = null,
        ?int $authorCount = null,
    ): Datum {
        $approvedDatum = DB::transaction(function () use ($reviewer, $datum, $point, $scoreOptionId, $publicationTier, $authorCount): Datum {
            $lockedDatum = Datum::query()
                ->with([
                    'criterion.report',
                    'criterion.criterionEvaluations',
                    'criterion.formula',
                    'user.ratingWorkplace.department',
                ])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('overrideCancellation', $lockedDatum);

            User::query()
                ->whereKey($lockedDatum->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $maximumPoint = $this->maximumResolver->handle($lockedDatum);

            $scoreOption = null;
            if (in_array($lockedDatum->criterion->code, [
                EducationalContentCriterionRule::CODE,
                ForeignLanguageCertificateCriterionRule::CODE,
            ], true)) {
                $scoreOption = CriterionManualScoreOption::query()
                    ->whereKey($scoreOptionId)
                    ->where('criterion_id', $lockedDatum->criterion_id)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();
                if ($lockedDatum->criterion->code === ForeignLanguageCertificateCriterionRule::CODE) {
                    $department = $lockedDatum->user?->ratingWorkplace?->department;
                    $point = $scoreOption === null
                        ? null
                        : ForeignLanguageCertificateCriterionRule::pointFor(
                            $scoreOption->code,
                            (string) $lockedDatum->user?->degree,
                            $department?->getKey(),
                            $department?->parent_id,
                        );
                } else {
                    $point = $scoreOption === null || $maximumPoint === null
                        ? null
                        : EducationalContentCriterionRule::pointFor($maximumPoint, $scoreOption->code);
                }

                $alreadyUsed = $lockedDatum->criterion->code === EducationalContentCriterionRule::CODE
                    && $scoreOption !== null && Datum::query()
                        ->where('user_id', $lockedDatum->user_id)
                        ->where('criterion_id', $lockedDatum->criterion_id)
                        ->where('status', 'accepted')
                        ->where('manual_score_option_id', $scoreOption->getKey())
                        ->where('id', '!=', $lockedDatum->getKey())
                        ->exists();

                if ($point === null || $alreadyUsed) {
                    throw ValidationException::withMessages([
                        'score_option_id' => $alreadyUsed
                            ? 'Bu foydalanuvchining 1.1 mezonida ushbu resurs turi allaqachon tasdiqlangan.'
                            : 'Ushbu mezon uchun qo‘llab-quvvatlanadigan daraja yoki resurs turini tanlang.',
                    ]);
                }
            }

            if ($lockedDatum->criterion->usesPublicationTierAiHumanReviewScore()) {
                if ($publicationTier === null || ! array_key_exists(
                    $publicationTier,
                    ScientificPublicationHumanReviewScoreCalculator::PUBLICATION_TIER_POINTS,
                )) {
                    throw ValidationException::withMessages([
                        'publication_tier' => 'Jurnal kvartili yoki nashr turini tanlang.',
                    ]);
                }

                $point = $this->scientificPublicationScoreCalculator->publicationTierPoint($publicationTier);
            }

            if ($lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore()) {
                if ($authorCount === null || $authorCount < 1 || $authorCount > 1000) {
                    throw ValidationException::withMessages([
                        'author_count' => 'Mualliflar soni 1 dan 1000 gacha bo‘lishi kerak.',
                    ]);
                }

                $point = $this->oakArticleScoreCalculator->calculate(
                    (string) $lockedDatum->user->degree,
                    $authorCount,
                );
            }

            if ($lockedDatum->criterion->usesAutomaticAiHumanReviewScore()) {
                $point = FixedPerResourceHumanReviewCriterionRule::pointFor(
                    (string) $lockedDatum->criterion->code,
                    (string) $lockedDatum->user->degree,
                ) ?? $maximumPoint;
            }

            if ($maximumPoint === null
                || $point === null
                || ! is_finite($point)
                || $point < 0
                || $point > $maximumPoint) {
                throw ValidationException::withMessages([
                    'point' => 'Kiritilgan ball foydalanuvchi uchun belgilangan chegaraga mos emas.',
                ]);
            }

            $point = round($point, 4);
            $aiDecision = $this->latestDecisionWasAi($lockedDatum);
            $scoreDescription = match (true) {
                $lockedDatum->criterion->usesAutomaticAiHumanReviewScore() => 'Serverda avtomatik hisoblangan ball: ',
                $lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore() => 'Bazaviy ball: '
                    .number_format(
                        $this->oakArticleScoreCalculator->basePoint((string) $lockedDatum->user->degree),
                        2,
                        '.',
                        '',
                    )
                    .' / '.$authorCount.' muallif. Hisoblangan ball: ',
                $lockedDatum->criterion->usesPublicationTierAiHumanReviewScore() => 'Tanlangan kvartil yoki nashr turi: '
                    .mb_strtoupper((string) $publicationTier).'. Hisoblangan ball: ',
                $scoreOption === null => 'Qo‘lda kiritilgan ball: ',
                default => 'Tanlangan daraja yoki resurs turi: '
                    .data_get($scoreOption->label, 'uz', $scoreOption->code).'. Hisoblangan ball: ',
            };
            $auditMessage = ($aiDecision
                ? 'Gemini rad etgan resurs'
                : 'Oldin rad etilgan resurs')
                .' inson tekshiruvida tasdiqlandi. '
                .$scoreDescription
                .number_format($point, 4, '.', '').'. '
                .'Maksimal ruxsat etilgan ball: '.number_format($maximumPoint, 4, '.', '').'.';

            $lockedDatum->update([
                'status' => 'accepted',
                'point' => $point,
                'manual_score_option_id' => $scoreOption?->getKey(),
                'author_count' => $lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore()
                    ? $authorCount
                    : $lockedDatum->author_count,
                'impact_factor' => $lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore()
                    ? null
                    : $lockedDatum->impact_factor,
                'publication_tier' => $lockedDatum->criterion->usesPublicationTierAiHumanReviewScore()
                    ? $publicationTier
                    : $lockedDatum->publication_tier,
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
            $identifiers = $this->fingerprintGenerator->forDatum($lockedDatum);
            $this->identifierRegistry->storeInactive(
                $lockedDatum,
                $lockedDatum->criterion->report_id,
                $identifiers,
            );
            $this->identifierRegistry->activate($lockedDatum);

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
