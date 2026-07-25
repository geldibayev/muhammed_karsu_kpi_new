<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferDatumCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $datum = $this->route('datum');

        return $datum instanceof Datum
            && $this->user()?->can('transferCriterion', $datum) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $datum = $this->route('datum');
        $currentCriterion = $datum instanceof Datum
            ? $datum->loadMissing('criterion:id,report_id')->criterion
            : null;
        $currentCriterionId = $datum instanceof Datum ? $datum->criterion_id : 0;
        $reportId = $currentCriterion?->report_id ?? 0;
        $resourceType = $datum instanceof Datum ? data_get($datum->material, 'type') : null;

        return [
            'criterion_id' => [
                'required',
                'integer',
                Rule::notIn([$currentCriterionId]),
                Rule::exists(Criterion::class, 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('report_id', $reportId)
                        ->where('status', '1')
                        ->where('upload', '1')
                        ->whereNotNull('parent_id')
                        ->where(function (Builder $resourceQuery) use ($resourceType): void {
                            $resourceQuery->where('res_type', 'all');

                            if (in_array($resourceType, ['file', 'url'], true)) {
                                $resourceQuery->orWhere('res_type', $resourceType);
                            }
                        }),
                ),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('criterion_id')) {
                    return;
                }

                $datum = $this->route('datum');
                $criterion = Criterion::query()->find($this->integer('criterion_id'));

                if (! $datum instanceof Datum || $criterion === null || $criterion->file_limit === 0) {
                    return;
                }

                $submissionCount = Datum::query()
                    ->where('user_id', $datum->user_id)
                    ->where('criterion_id', $criterion->getKey())
                    ->countsTowardsUploadLimit()
                    ->count();

                if ($submissionCount >= $criterion->file_limit) {
                    $validator->errors()->add(
                        'criterion_id',
                        'Tanlangan kriteriyada foydalanuvchi uchun yuklash chegarasi to‘lgan.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'criterion_id.required' => 'O‘tkazish uchun kriteriyani tanlang.',
            'criterion_id.not_in' => 'Joriy kriteriyadan boshqa kriteriyani tanlang.',
            'criterion_id.exists' => 'Tanlangan kriteriyaga ushbu resursni o‘tkazib bo‘lmaydi.',
        ];
    }
}
