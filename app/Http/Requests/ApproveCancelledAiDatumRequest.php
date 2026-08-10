<?php

namespace App\Http\Requests;

use App\Actions\ResolveAiManualPointMaximum;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use App\Support\EducationalContentCriterionRule;
use App\Support\ForeignLanguageCertificateCriterionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveCancelledAiDatumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('overrideCancellation', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ResolveAiManualPointMaximum $maximumResolver): array
    {
        $datum = $this->route('datum');
        $maximumPoint = $datum instanceof Datum
            ? $maximumResolver->handle($datum)
            : null;
        $isEducationalContentCriterion = $datum instanceof Datum
            && $datum->criterion()->where('code', EducationalContentCriterionRule::CODE)->exists();
        $isForeignLanguageCertificateCriterion = $datum instanceof Datum
            && $datum->criterion()->where('code', ForeignLanguageCertificateCriterionRule::CODE)->exists();
        $usesPublicationTierScore = $datum instanceof Datum
            && $datum->loadMissing('criterion')->criterion?->usesPublicationTierAiHumanReviewScore() === true;
        $usesScoreOption = $isEducationalContentCriterion || $isForeignLanguageCertificateCriterion;

        return [
            'point' => [
                Rule::requiredIf(! $usesScoreOption && ! $usesPublicationTierScore),
                Rule::prohibitedIf($usesScoreOption || $usesPublicationTierScore),
                'nullable',
                'numeric',
                'min:0',
                'max:'.($maximumPoint ?? 0),
            ],
            'score_option_id' => [
                Rule::requiredIf($usesScoreOption),
                Rule::prohibitedIf(! $usesScoreOption),
                'nullable',
                'integer',
                Rule::exists(CriterionManualScoreOption::class, 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('criterion_id', $datum instanceof Datum ? $datum->criterion_id : 0)
                        ->where('active', true)),
            ],
            'publication_tier' => [
                Rule::requiredIf($usesPublicationTierScore),
                Rule::prohibitedIf(! $usesPublicationTierScore),
                'nullable',
                'string',
                Rule::in(array_keys(ScientificPublicationHumanReviewScoreCalculator::PUBLICATION_TIER_POINTS)),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'point.required' => 'Tasdiqlash uchun ballni kiriting.',
            'point.numeric' => 'Ball raqam bo‘lishi kerak.',
            'point.min' => 'Ball 0 dan kam bo‘lishi mumkin emas.',
            'point.max' => 'Kiritilgan ball foydalanuvchi uchun belgilangan maksimal chegaradan oshdi.',
            'point.prohibited' => 'Bu mezon uchun ball serverda kvartil bo‘yicha hisoblanadi.',
            'score_option_id.required' => 'Tasdiqlash uchun bo‘sh resurs turini tanlang.',
            'score_option_id.exists' => 'Tanlangan resurs turi ushbu mezonga tegishli emas.',
            'publication_tier.required' => 'Jurnal kvartili yoki nashr turini tanlang.',
            'publication_tier.prohibited' => 'Bu mezon uchun kvartil yoki nashr turi yuborilmaydi.',
            'publication_tier.in' => 'Tanlangan jurnal kvartili yoki nashr turi noto‘g‘ri.',
        ];
    }
}
