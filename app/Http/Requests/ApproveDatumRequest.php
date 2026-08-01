<?php

namespace App\Http\Requests;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveDatumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum && $this->user()?->can('review', $datum) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $datum = $this->route('datum');
        $criterion = $datum instanceof Datum
            ? $datum->loadMissing(['criterion.criterionEvaluations', 'user'])->criterion
            : null;
        $criterionId = $datum instanceof Datum ? $datum->criterion_id : 0;
        $isManualCriterion = $criterion?->checking === 'manual';
        $isAiCriterion = $criterion?->checking === 'ai';
        $isOakArticleCriterion = $criterion?->isOakArticleCriterion() === true;
        $activeScoreOptionCount = $isManualCriterion
            ? CriterionManualScoreOption::query()
                ->where('criterion_id', $criterionId)
                ->where('active', true)
                ->count()
            : 0;
        $evaluationCategory = $datum instanceof Datum ? $datum->user?->degree : null;
        $evaluationMaximum = $criterion?->criterionEvaluations
            ->firstWhere('evaluation', $evaluationCategory)?->score;
        $reviewerPointMaximum = $criterion?->aiSubmissionMaximum((float) $evaluationMaximum) ?? 0;

        return [
            'score_option_id' => [
                Rule::requiredIf($isManualCriterion && $activeScoreOptionCount !== 1),
                Rule::prohibitedIf(! $isManualCriterion),
                'nullable',
                'integer',
                Rule::exists(CriterionManualScoreOption::class, 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('criterion_id', $criterionId)
                        ->where('active', true)),
            ],
            'point' => [
                Rule::requiredIf($isAiCriterion && ! $isOakArticleCriterion),
                Rule::prohibitedIf(! $isAiCriterion || $isOakArticleCriterion),
                'nullable',
                'numeric',
                'min:0',
                'max:'.$reviewerPointMaximum,
            ],
            'author_count' => [
                Rule::requiredIf($isAiCriterion && $isOakArticleCriterion),
                Rule::prohibitedIf(! $isAiCriterion || ! $isOakArticleCriterion),
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'score_option_id.required' => 'Tasdiqlash uchun tavsifdagi baholash variantini tanlang.',
            'score_option_id.prohibited' => 'Bu mezon uchun manual baholash varianti yuborilmaydi.',
            'score_option_id.exists' => 'Tanlangan baholash varianti ushbu mezonga tegishli emas.',
            'point.required' => 'AI tekshiruvidan qolgan resurs uchun aniq ballni kiriting.',
            'point.prohibited' => 'Bu mezon uchun alohida ball yuborilmaydi.',
            'point.max' => 'Kiritilgan ball ushbu submission uchun ruxsat etilgan chegaradan oshdi.',
            'author_count.required' => 'Tasdiqlash uchun maqoladagi jami mualliflar sonini kiriting.',
            'author_count.prohibited' => 'Bu mezon uchun mualliflar soni alohida yuborilmaydi.',
        ];
    }
}
