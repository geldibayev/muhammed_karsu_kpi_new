<?php

namespace App\Http\Requests;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use App\Support\EducationalContentCriterionRule;
use App\Support\InternationalCooperationCriterionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveDatumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && ($this->user()?->can('review', $datum) === true
                || $this->user()?->can('correctAcceptedScore', $datum) === true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $datum = $this->route('datum');
        $criterion = $datum instanceof Datum
            ? $datum->loadMissing(['criterion.criterionEvaluations', 'user'])->criterion
            : null;
        $criterionId = $datum instanceof Datum ? $datum->criterion_id : 0;
        $isManualCriterion = $criterion?->checking === 'manual';
        $isEducationalContentCriterion = $criterion?->code === EducationalContentCriterionRule::CODE;
        $isAiCriterion = $criterion?->checking === 'ai';
        $usesDegreeBasedArticleScore = $criterion?->usesDegreeBasedAuthorDividedArticleScore() === true;
        $isPrintedLiteratureCriterion = $criterion?->isPrintedEducationalLiteratureCriterion() === true;
        $usesAutomaticAiHumanReviewScore = $criterion?->usesAutomaticAiHumanReviewScore() === true;
        $usesPublicationTierScore = $criterion?->usesPublicationTierAiHumanReviewScore() === true;
        $usesAuthorDividedScore = $criterion?->usesAuthorDividedAiHumanReviewScore() === true;
        $usesUniversityTierScore = $criterion?->usesUniversityTierAiHumanReviewScore() === true;
        $isIndustryFundingCriterion = $criterion?->isIndustryFundingCriterion() === true;
        $activeScoreOptionCount = $isManualCriterion
            ? CriterionManualScoreOption::query()
                ->where('criterion_id', $criterionId)
                ->where('active', true)
                ->count()
            : 0;
        $evaluationCategory = $datum instanceof Datum ? $datum->user?->degree : null;
        $evaluationMaximum = $criterion?->criterionEvaluations
            ->firstWhere('evaluation', $evaluationCategory)?->score;
        $reviewerPointMaximum = $criterion?->aiSubmissionMaximum((float) $evaluationMaximum) ?? 0;

        return [
            'score_option_id' => [
                Rule::requiredIf($isManualCriterion
                    && ($isEducationalContentCriterion || $activeScoreOptionCount !== 1)),
                Rule::prohibitedIf(! $isManualCriterion),
                'nullable',
                'integer',
                Rule::exists(CriterionManualScoreOption::class, 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('criterion_id', $criterionId)
                        ->where('active', true)),
            ],
            'point' => [
                Rule::requiredIf($isAiCriterion
                    && ! $usesDegreeBasedArticleScore
                    && ! $isPrintedLiteratureCriterion
                    && ! $usesAutomaticAiHumanReviewScore
                    && ! $usesPublicationTierScore
                    && ! $usesAuthorDividedScore
                    && ! $usesUniversityTierScore
                    && ! $isIndustryFundingCriterion),
                Rule::prohibitedIf(! $isAiCriterion
                    || $usesDegreeBasedArticleScore
                    || $isPrintedLiteratureCriterion
                    || $usesAutomaticAiHumanReviewScore
                    || $usesPublicationTierScore
                    || $usesAuthorDividedScore
                    || $usesUniversityTierScore
                    || $isIndustryFundingCriterion),
                'nullable',
                'numeric',
                'min:0',
                'max:'.$reviewerPointMaximum,
            ],
            'author_count' => [
                Rule::requiredIf($isAiCriterion
                    && ($usesDegreeBasedArticleScore
                        || $isPrintedLiteratureCriterion
                        || $usesAuthorDividedScore
                        || $isIndustryFundingCriterion)),
                Rule::prohibitedIf(! $isAiCriterion
                    || (! $usesDegreeBasedArticleScore
                        && ! $isPrintedLiteratureCriterion
                        && ! $usesAuthorDividedScore
                        && ! $isIndustryFundingCriterion)),
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'page_count' => [
                Rule::requiredIf($isAiCriterion && $isPrintedLiteratureCriterion),
                Rule::prohibitedIf(! $isAiCriterion || ! $isPrintedLiteratureCriterion),
                'nullable',
                'integer',
                'min:1',
                'max:100000',
            ],
            'impact_factor' => [
                'prohibited',
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'publication_tier' => [
                Rule::requiredIf($usesPublicationTierScore),
                Rule::prohibitedIf(! $usesPublicationTierScore),
                'nullable',
                'string',
                Rule::in(array_keys(ScientificPublicationHumanReviewScoreCalculator::PUBLICATION_TIER_POINTS)),
            ],
            'university_tier' => [
                Rule::requiredIf($usesUniversityTierScore),
                Rule::prohibitedIf(! $usesUniversityTierScore),
                'nullable',
                'string',
                Rule::in(array_keys(InternationalCooperationCriterionRule::UNIVERSITY_TIER_POINTS)),
            ],
            'received_amount' => [
                Rule::requiredIf($isIndustryFundingCriterion),
                Rule::prohibitedIf(! $isIndustryFundingCriterion),
                'nullable',
                'numeric',
                'min:0.01',
                'max:9999999999999999.99',
                'decimal:0,2',
            ],
            'criterion' => [
                Rule::prohibitedIf(! $isAiCriterion),
                'nullable',
                'integer',
                Rule::in([$criterionId]),
            ],
            'page' => [
                Rule::prohibitedIf(! $isAiCriterion),
                'nullable',
                'integer',
                'min:1',
                'max:100000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'score_option_id.required' => 'Tasdiqlash uchun tavsifdagi baholash variantini tanlang.',
            'score_option_id.prohibited' => 'Bu mezon uchun manual baholash varianti yuborilmaydi.',
            'score_option_id.exists' => 'Tanlangan baholash varianti ushbu mezonga tegishli emas.',
            'point.required' => 'AI tekshiruvidan qolgan resurs uchun aniq ballni kiriting.',
            'point.prohibited' => 'Bu mezon uchun alohida ball yuborilmaydi.',
            'point.max' => 'Kiritilgan ball ushbu submission uchun ruxsat etilgan chegaradan oshdi.',
            'author_count.required' => 'Tasdiqlash uchun maqoladagi jami mualliflar sonini kiriting.',
            'author_count.prohibited' => 'Bu mezon uchun mualliflar soni alohida yuborilmaydi.',
            'page_count.required' => 'Tasdiqlash uchun kitobdagi jami sahifalar sonini kiriting.',
            'page_count.prohibited' => 'Bu mezon uchun sahifalar soni alohida yuborilmaydi.',
            'impact_factor.required' => 'Tasdiqlash uchun impakt faktorning butun son qiymatini kiriting.',
            'impact_factor.prohibited' => 'Bu kriteriya uchun impakt faktor yuborilmaydi.',
            'impact_factor.integer' => 'Impakt faktor butun son bo‘lishi kerak.',
            'publication_tier.required' => 'Jurnal kvartili yoki nashr turini tanlang.',
            'publication_tier.prohibited' => 'Bu kriteriya uchun kvartil yoki nashr turi yuborilmaydi.',
            'publication_tier.in' => 'Tanlangan jurnal kvartili yoki nashr turi noto‘g‘ri.',
            'university_tier.required' => 'Universitetning xalqaro reytingdagi Top darajasini tanlang.',
            'university_tier.prohibited' => 'Bu kriteriya uchun universitet Top darajasi yuborilmaydi.',
            'university_tier.in' => 'Tanlangan universitet Top darajasi noto‘g‘ri.',
            'received_amount.required' => 'Universitet hisobiga tushgan summani kiriting.',
            'received_amount.prohibited' => 'Bu kriteriya uchun tushgan summa yuborilmaydi.',
            'received_amount.decimal' => 'Summa ko‘pi bilan 2 ta kasr xonasiga ega bo‘lishi kerak.',
        ];
    }
}
