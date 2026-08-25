<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class StoreCriterionUploadPermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-upload-permissions') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'all_criteria' => ['nullable', 'boolean'],
            'criterion_ids' => ['required_unless:all_criteria,1', 'array', 'min:1'],
            'criterion_ids.*' => ['required', 'integer', 'distinct', 'exists:criteria,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = User::query()->find($this->integer('user_id'));
                if ($user === null || ! $user->isActive() || $user->isUploadBlocked()
                    || (! $user->hasRole('teacher') && ! $user->hasRole('user'))) {
                    $validator->errors()->add('user_id', 'Tanlangan foydalanuvchiga resurs yuklash ruxsatini berib bo‘lmaydi.');

                    return;
                }

                $criterionIds = collect($this->input('criterion_ids', []))
                    ->map(static fn (mixed $criterionId): int => (int) $criterionId);
                $eligibleCriterionCount = $this->eligibleCriteriaQuery($user)
                    ->when(! $this->boolean('all_criteria'), fn (Builder $query): Builder => $query->whereKey($criterionIds))
                    ->count();

                if ($eligibleCriterionCount === 0
                    || (! $this->boolean('all_criteria') && $eligibleCriterionCount !== $criterionIds->count())) {
                    $validator->errors()->add('criterion_ids', 'Tanlangan kriteriyalardan biri ushbu foydalanuvchi uchun yuklashga mos emas.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Foydalanuvchini tanlang.',
            'user_id.exists' => 'Tanlangan foydalanuvchi topilmadi.',
            'all_criteria.boolean' => 'Barcha kriteriyalar tanlovi noto‘g‘ri.',
            'criterion_ids.required_unless' => 'Kamida bitta kriteriyani tanlang yoki barcha mos kriteriyalarga ruxsat bering.',
            'criterion_ids.required' => 'Kamida bitta kriteriyani tanlang.',
            'criterion_ids.array' => 'Kriteriyalar ro‘yxatini to‘g‘ri tanlang.',
            'criterion_ids.min' => 'Kamida bitta kriteriyani tanlang.',
            'criterion_ids.*.distinct' => 'Bir kriteriya takroran tanlangan.',
            'criterion_ids.*.exists' => 'Tanlangan kriteriyalardan biri topilmadi.',
            'reason.required' => 'Maxsus ruxsat sababini yozing.',
            'reason.max' => 'Ruxsat sababi 5000 belgidan oshmasligi kerak.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /** @return Collection<int, int> */
    public function criterionIdsForGrant(User $user): Collection
    {
        return $this->eligibleCriteriaQuery($user)
            ->when(
                ! $this->boolean('all_criteria'),
                fn (Builder $query): Builder => $query->whereKey($this->validated('criterion_ids')),
            )
            ->pluck('id');
    }

    private function eligibleCriteriaQuery(User $user): Builder
    {
        return Criterion::query()
            ->whereNotNull('parent_id')
            ->where('status', '1')
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->where(fn (Builder $query): Builder => $query
                ->where('upload', '1')
                ->orWhere('code', Criterion::H_INDEX_CODE))
            ->whereHas('criterionEvaluations', fn (Builder $query): Builder => $query
                ->where('evaluation', $user->degree)
                ->where('has', '1'));
    }
}
