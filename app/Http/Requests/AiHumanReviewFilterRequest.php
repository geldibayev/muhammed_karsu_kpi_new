<?php

namespace App\Http\Requests;

use App\Models\Datum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AiHumanReviewFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-ai-human-reviews') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reviewerHemisId = (int) $this->user()->hemis_id;

        return [
            'criterion' => [
                'bail',
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($reviewerHemisId): void {
                    $hasPendingResource = Datum::query()
                        ->pendingAiHumanReviewFor($reviewerHemisId)
                        ->where('criterion_id', (int) $value)
                        ->exists();

                    if (! $hasPendingResource) {
                        $fail('Tanlangan kriteriya bo\'yicha sizga biriktirilgan resurs topilmadi.');
                    }
                },
            ],
        ];
    }
}
