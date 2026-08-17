<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\User;
use App\Services\OakArticleScoreCalculator;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateAcceptedDatumScore
{
    public function __construct(
        private ResolveAiManualPointMaximum $maximumResolver,
        private RecalculateReportPoints $recalculateReportPoints,
        private ScientificPublicationHumanReviewScoreCalculator $scientificPublicationScoreCalculator,
        private OakArticleScoreCalculator $oakArticleScoreCalculator,
    ) {}

    public function handle(
        User $reviewer,
        Datum $datum,
        ?float $point,
        string $reason,
        ?string $publicationTier = null,
        ?int $authorCount = null,
    ): Datum {
        $updatedDatum = DB::transaction(function () use ($reviewer, $datum, $point, $reason, $publicationTier, $authorCount): Datum {
            $lockedDatum = Datum::query()
                ->with(['criterion.report', 'criterion.criterionEvaluations', 'criterion.formula', 'user'])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('updateAcceptedScore', $lockedDatum);

            $maximumPoint = $this->maximumResolver->handle($lockedDatum);
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

            if ($maximumPoint === null || $point === null || ! is_finite($point) || $point < 0 || $point > $maximumPoint) {
                throw ValidationException::withMessages([
                    'point' => 'Kiritilgan ball foydalanuvchi uchun belgilangan chegaraga mos emas.',
                ]);
            }

            $previousPoint = (float) $lockedDatum->point;
            $point = round($point, 4);
            $actorLabel = $reviewer->isSuperAdmin() ? 'Super administrator' : 'Kriteriya mas’uli';
            $auditMessage = $actorLabel.' tasdiqlangan resurs ballini '
                .number_format($previousPoint, 4, '.', '').' dan '
                .number_format($point, 4, '.', '').' ga o‘zgartirdi. Sabab: '.trim($reason);

            $lockedDatum->update([
                'point' => $point,
                'publication_tier' => $lockedDatum->criterion->usesPublicationTierAiHumanReviewScore()
                    ? $publicationTier
                    : $lockedDatum->publication_tier,
                'author_count' => $lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore()
                    ? $authorCount
                    : $lockedDatum->author_count,
                'impact_factor' => $lockedDatum->criterion->usesDegreeBasedAuthorDividedArticleScore()
                    ? null
                    : $lockedDatum->impact_factor,
                'reason' => $auditMessage,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'warning',
                'message' => $auditMessage,
                'message_type' => $reviewer->isSuperAdmin()
                    ? 'accepted_score_updated_by_super_admin'
                    : 'accepted_score_updated_by_reviewer',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($updatedDatum->criterion->report);

        return $updatedDatum->refresh();
    }
}
