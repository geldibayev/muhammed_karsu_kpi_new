<?php

namespace App\Http\Requests;

use App\Actions\CorrectHIndexProfileValue;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHIndexProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('updateHIndexProfile', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile' => ['required', 'string', Rule::in(array_keys(CorrectHIndexProfileValue::PROFILES))],
            'expected_value' => ['required', 'integer', 'min:0'],
            'new_value' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'profile.in' => 'H-index bazasi noto‘g‘ri tanlangan.',
            'expected_value.integer' => 'Joriy H-index butun son bo‘lishi kerak.',
            'new_value.required' => 'Yangi H-index qiymatini kiriting.',
            'new_value.integer' => 'H-index butun son bo‘lishi kerak.',
            'new_value.min' => 'H-index 0 dan kam bo‘lishi mumkin emas.',
        ];
    }
}
