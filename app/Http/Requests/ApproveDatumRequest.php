<?php

namespace App\Http\Requests;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
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

        return $datum instanceof Datum && $this->user()?->can('review', $datum) === true;
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
            ? $datum->loadMissing('criterion:id,checking')->criterion
            : null;
        $criterionId = $datum instanceof Datum ? $datum->criterion_id : 0;
        $isManualCriterion = $criterion?->checking === 'manual';

        return [
            'score_option_id' => [
                Rule::requiredIf($isManualCriterion),
                Rule::prohibitedIf(! $isManualCriterion),
                'nullable',
                'integer',
                Rule::exists(CriterionManualScoreOption::class, 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('criterion_id', $criterionId)
                        ->where('active', true)),
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
        ];
    }
}
