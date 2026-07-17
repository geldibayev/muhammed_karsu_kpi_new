<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $criterion = $this->route('criterion');

        return $criterion instanceof Criterion
            && $this->user()?->can('update', $criterion) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ai_prompt' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
