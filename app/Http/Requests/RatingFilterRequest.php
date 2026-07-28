<?php

namespace App\Http\Requests;

use App\Enums\RatingMode;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'mode' => ['nullable', Rule::enum(RatingMode::class)],
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

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $facultyId = $this->integer('faculty');
                $departmentId = $this->integer('department');

                if ($facultyId === 0 || $departmentId === 0) {
                    if ($facultyId !== 0 && ! Department::query()->faculties()->whereKey($facultyId)->exists()) {
                        $validator->errors()->add('faculty', 'Tanlangan tuzilma fakultet emas.');
                    }

                    return;
                }

                if (! Department::query()->faculties()->whereKey($facultyId)->exists()) {
                    $validator->errors()->add('faculty', 'Tanlangan tuzilma fakultet emas.');

                    return;
                }

                $belongsToFaculty = Department::query()
                    ->whereKey($departmentId)
                    ->where('parent_id', $facultyId)
                    ->exists();

                if (! $belongsToFaculty) {
                    $validator->errors()->add('department', 'Tanlangan kafedra ushbu fakultetga tegishli emas.');
                }
            },
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
