<?php

namespace App\Http\Requests;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEducationalContentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('changeEducationalContentType', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $datum = $this->route('datum');
        $criterionId = $datum instanceof Datum ? $datum->criterion_id : 0;

        return [
            'score_option_id' => [
                'required',
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
            'score_option_id.required' => 'Resurs turini tanlang.',
            'score_option_id.exists' => 'Tanlangan resurs turi 1.1 mezoniga tegishli emas.',
        ];
    }
}
