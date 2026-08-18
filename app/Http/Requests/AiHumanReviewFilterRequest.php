<?php

namespace App\Http\Requests;

use App\Enums\DatumStatus;
use App\Models\AiHumanReviewAssignment;
use App\Models\Datum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $selectedStatus = $this->string('status')->toString() ?: 'pending';

        return [
            'status' => ['nullable', Rule::in(['pending', DatumStatus::Accepted->value, DatumStatus::Cancelled->value])],
            'criterion' => [
                'bail',
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($selectedStatus, $user): void {
                    $hasResource = Datum::query()
                        ->when($selectedStatus === 'pending', function (Builder $query) use ($user): Builder {
                            return $user->isSuperAdmin()
                                ? $query->pendingAiHumanReviews((int) $user->hemis_id)
                                : $query->pendingAiHumanReviewFor((int) $user->hemis_id);
                        }, function (Builder $query) use ($selectedStatus, $user): Builder {
                            return $query
                                ->where('status', $selectedStatus)
                                ->whereHas('criterion', function (Builder $query) use ($user): void {
                                    $query->where('checking', 'ai');

                                    if (! $user->isSuperAdmin()) {
                                        $query->whereIn(
                                            'code',
                                            AiHumanReviewAssignment::criterionCodesFor((int) $user->hemis_id),
                                        );
                                    }
                                });
                        })
                        ->where('criterion_id', (int) $value)
                        ->exists();

                    if (! $hasResource) {
                        $fail($user->isSuperAdmin()
                            ? 'Tanlangan kriteriya bo\'yicha AI resurs topilmadi.'
                            : 'Tanlangan kriteriya bo\'yicha sizga biriktirilgan resurs topilmadi.');
                    }
                },
            ],
        ];
    }
}
