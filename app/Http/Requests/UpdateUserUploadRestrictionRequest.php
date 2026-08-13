<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserUploadRestrictionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && $this->user()?->can('manageUploadRestriction', $target) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'blocked' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf($this->boolean('blocked')),
                Rule::prohibitedIf(! $this->boolean('blocked')),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Bloklash sababini yozing.',
            'reason.max' => 'Bloklash sababi 5000 belgidan oshmasligi kerak.',
        ];
    }
}
