<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use App\Models\CriterionUploadPermission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'criterion_id' => ['required', 'integer', 'exists:criteria,id'],
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
                $criterion = Criterion::query()->find($this->integer('criterion_id'));

                if ($user === null || ! $user->isActive() || $user->isUploadBlocked()
                    || (! $user->hasRole('teacher') && ! $user->hasRole('user'))) {
                    $validator->errors()->add('user_id', 'Tanlangan foydalanuvchiga resurs yuklash ruxsatini berib bo‘lmaydi.');

                    return;
                }

                if ($criterion === null
                    || ($criterion->upload !== '1' && ! $criterion->isHIndexCriterion())
                    || $criterion->status !== '1'
                    || ! $criterion->report()->where('status', '1')->exists()
                    || ! $criterion->criterionEvaluations()
                        ->where('evaluation', $user->degree)
                        ->where('has', '1')
                        ->exists()) {
                    $validator->errors()->add('criterion_id', 'Tanlangan kriteriya ushbu foydalanuvchi uchun yuklashga mos emas.');

                    return;
                }

                if (CriterionUploadPermission::query()
                    ->available()
                    ->whereBelongsTo($user)
                    ->whereBelongsTo($criterion)
                    ->exists()) {
                    $validator->errors()->add('criterion_id', 'Bu foydalanuvchi uchun ushbu kriteriyaga ruxsat allaqachon berilgan.');
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
            'criterion_id.required' => 'Kriteriyani tanlang.',
            'criterion_id.exists' => 'Tanlangan kriteriya topilmadi.',
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
}
