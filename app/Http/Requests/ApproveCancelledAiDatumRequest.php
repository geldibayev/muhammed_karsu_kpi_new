<?php

namespace App\Http\Requests;

use App\Actions\ResolveAiManualPointMaximum;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCancelledAiDatumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('overrideAiCancellation', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ResolveAiManualPointMaximum $maximumResolver): array
    {
        $datum = $this->route('datum');
        $maximumPoint = $datum instanceof Datum
            ? $maximumResolver->handle($datum)
            : null;

        return [
            'point' => [
                'required',
                'numeric',
                'min:0',
                'max:'.($maximumPoint ?? 0),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'point.required' => 'Tasdiqlash uchun ballni kiriting.',
            'point.numeric' => 'Ball raqam bo‘lishi kerak.',
            'point.min' => 'Ball 0 dan kam bo‘lishi mumkin emas.',
            'point.max' => 'Kiritilgan ball foydalanuvchi uchun belgilangan maksimal chegaradan oshdi.',
        ];
    }
}
