<?php

namespace App\Http\Requests;

use App\Models\CriterionReviewerAssignment;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualReviewFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-manual-reviews') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'cancelled'])],
            'criterion' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! CriterionReviewerAssignment::query()
                        ->where('hemis_id', $this->user()?->hemis_id)
                        ->where('criterion_id', (int) $value)
                        ->exists()) {
                        $fail('Tanlangan kriteriya sizga biriktirilmagan.');
                    }
                },
            ],
        ];
    }
}
