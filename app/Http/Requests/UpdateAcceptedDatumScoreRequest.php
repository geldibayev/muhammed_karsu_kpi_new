<?php

namespace App\Http\Requests;

use App\Actions\ResolveAiManualPointMaximum;
use App\Models\Datum;
use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcceptedDatumScoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('updateAcceptedScore', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ResolveAiManualPointMaximum $maximumResolver): array
    {
        $datum = $this->route('datum');
        $maximumPoint = $datum instanceof Datum ? $maximumResolver->handle($datum) : 0;
        $usesPublicationTierScore = $datum instanceof Datum
            && $datum->loadMissing('criterion')->criterion?->usesPublicationTierAiHumanReviewScore() === true;

        return [
            'point' => [
                Rule::requiredIf(! $usesPublicationTierScore),
                Rule::prohibitedIf($usesPublicationTierScore),
                'nullable',
                'numeric',
                'min:0',
                'max:'.($maximumPoint ?? 0),
            ],
            'publication_tier' => [
                Rule::requiredIf($usesPublicationTierScore),
                Rule::prohibitedIf(! $usesPublicationTierScore),
                'nullable',
                'string',
                Rule::in(array_keys(ScientificPublicationHumanReviewScoreCalculator::PUBLICATION_TIER_POINTS)),
            ],
            'score_change_reason' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('score_change_reason'))) {
            $this->merge(['score_change_reason' => trim($this->input('score_change_reason'))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'point.required' => 'Yangi ballni kiriting.',
            'point.numeric' => 'Ball raqam bo‘lishi kerak.',
            'point.min' => 'Ball 0 dan kam bo‘lishi mumkin emas.',
            'point.max' => 'Ball belgilangan maksimal chegaradan oshmasligi kerak.',
            'point.prohibited' => 'Bu mezon uchun ball serverda kvartil bo‘yicha hisoblanadi.',
            'publication_tier.required' => 'Jurnal kvartili yoki nashr turini tanlang.',
            'publication_tier.prohibited' => 'Bu mezon uchun kvartil yoki nashr turi yuborilmaydi.',
            'publication_tier.in' => 'Tanlangan jurnal kvartili yoki nashr turi noto‘g‘ri.',
            'score_change_reason.required' => 'Ballni o‘zgartirish sababini yozing.',
            'score_change_reason.max' => 'Sabab 5000 belgidan oshmasligi kerak.',
        ];
    }
}
