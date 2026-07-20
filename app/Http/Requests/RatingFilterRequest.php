<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'degree_group' => ['nullable', 'string', Rule::in(['with_degree', 'without_degree'])],
            'faculty' => [
                'nullable',
                'integer',
                Rule::exists(Department::class, 'id')->whereNull('parent_id'),
            ],
            'department' => [
                'nullable',
                'integer',
                Rule::exists(Department::class, 'id')->whereNotNull('parent_id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge([
                'search' => preg_replace('/\s+/u', ' ', trim($this->string('search')->toString())),
            ]);
        }
    }
}
