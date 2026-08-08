<?php

namespace App\Http\Requests;

use App\Models\Datum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
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
        $user = $this->user();

        return [
            'criterion' => [
                'bail',
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    $hasPendingResource = Datum::query()
                        ->when(
                            $user->isSuperAdmin(),
                            fn (Builder $query): Builder => $query->pendingAiHumanReviews(),
                            fn (Builder $query): Builder => $query
                                ->pendingAiHumanReviewFor((int) $user->hemis_id),
                        )
                        ->where('criterion_id', (int) $value)
                        ->exists();

                    if (! $hasPendingResource) {
                        $fail($user->isSuperAdmin()
                            ? 'Tanlangan kriteriya bo\'yicha baholanmagan AI resurs topilmadi.'
                            : 'Tanlangan kriteriya bo\'yicha sizga biriktirilgan resurs topilmadi.');
                    }
                },
            ],
        ];
    }
}
