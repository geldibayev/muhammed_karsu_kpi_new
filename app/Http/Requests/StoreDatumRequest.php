<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreDatumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $criterion = $this->route('upload');

        return $criterion instanceof Criterion
            && $this->user()?->can('submit', $criterion) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $criterion = $this->route('upload');
        $allowedResourceTypes = $criterion instanceof Criterion && $criterion->res_type !== 'all'
            ? [$criterion->res_type]
            : ['file', 'url'];

        if ($criterion instanceof Criterion && $criterion->isHIndexCriterion()) {
            return [
                'uploadResourceType' => ['required', Rule::in(['h_index'])],
                'year' => $this->yearRules($criterion),
                'h_index' => ['required', 'array:scopus,web_of_science,research_gate'],
                'h_index.scopus' => ['required', 'array:link,value'],
                'h_index.scopus.link' => ['required', 'url:http,https', 'max:255'],
                'h_index.scopus.value' => ['required', 'integer', 'min:0'],
                'h_index.web_of_science' => ['required', 'array:link,value'],
                'h_index.web_of_science.link' => ['required', 'url:http,https', 'max:255'],
                'h_index.web_of_science.value' => ['required', 'integer', 'min:0'],
                'h_index.research_gate' => ['required', 'array:link,value'],
                'h_index.research_gate.link' => ['required', 'url:http,https', 'max:255'],
                'h_index.research_gate.value' => ['required', 'integer', 'min:0'],
            ];
        }

        $rules = [
            'uploadResourceType' => ['required', Rule::in($allowedResourceTypes)],
            'year' => $this->yearRules($criterion),
            'uploadResourceFile' => [
                'nullable',
                Rule::requiredIf($this->input('uploadResourceType') === 'file'),
                Rule::prohibitedIf($this->input('uploadResourceType') !== 'file'),
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max('2mb'),
            ],
            'uploadResourceUrl' => [
                'nullable',
                Rule::requiredIf($this->input('uploadResourceType') === 'url'),
                Rule::prohibitedIf($this->input('uploadResourceType') !== 'url'),
                'string',
                'url:http,https',
                'max:255',
            ],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'article' => ['nullable', 'array:name,keywords,lang,authors_num,authors,doi,journal,params'],
            'article.name' => ['nullable', 'string', 'max:1000'],
            'article.keywords' => ['nullable', 'string', 'max:2000'],
            'article.lang' => ['nullable', 'integer', 'exists:languages,id'],
            'article.authors_num' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'article.authors' => ['nullable', 'string', 'max:5000'],
            'article.doi' => ['nullable', 'string', 'max:255'],
            'article.journal' => ['nullable', 'string', 'max:1000'],
            'article.params' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array:name,division,authors,publisher,publish_params,certificate_no,certificate_date,form'],
            'data.name' => ['nullable', 'string', 'max:1000'],
            'data.division' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'data.authors' => ['nullable', 'string', 'max:5000'],
            'data.publisher' => ['nullable', 'string', 'max:1000'],
            'data.publish_params' => ['nullable', 'string', 'max:2000'],
            'data.certificate_no' => ['nullable', 'string', 'max:255'],
            'data.certificate_date' => ['nullable', 'date_format:Y-m-d'],
            'data.form' => ['nullable', 'integer', 'between:10,17'],
        ];

        return array_replace($rules, $this->templateRules($criterion));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function templateRules(mixed $criterion): array
    {
        if (! $criterion instanceof Criterion) {
            return [];
        }

        return match ((string) $criterion->template) {
            '1' => [
                'language_id' => ['required', 'integer', 'exists:languages,id'],
                'data' => ['required', 'array:name,division,authors,publisher,publish_params,certificate_no,certificate_date,form'],
                'data.name' => ['required', 'string', 'max:1000'],
                'data.division' => ['required', 'integer', 'min:1', 'max:1000'],
                'data.authors' => ['required', 'string', 'max:5000'],
                'data.publisher' => ['required', 'string', 'max:1000'],
                'data.publish_params' => ['required', 'string', 'max:2000'],
            ],
            '2', '3' => [
                'article' => ['required', 'array:name,keywords,lang,authors_num,authors,doi,journal,params'],
                'article.name' => ['required', 'string', 'max:1000'],
                'article.keywords' => ['required', 'string', 'max:2000'],
                'article.lang' => ['required', 'integer', 'exists:languages,id'],
                'article.authors_num' => ['required', 'integer', 'min:1', 'max:1000'],
                'article.authors' => ['required', 'string', 'max:5000'],
                'article.journal' => ['required', 'string', 'max:1000'],
                'article.params' => ['required', 'string', 'max:2000'],
            ],
            '4' => [
                'data' => ['required', 'array:name,division,authors,publisher,publish_params,certificate_no,certificate_date,form'],
                'data.name' => ['required', 'string', 'max:1000'],
                'data.division' => ['required', 'integer', 'min:1', 'max:1000'],
                'data.authors' => ['required', 'string', 'max:5000'],
                'data.publish_params' => ['required', 'string', 'max:2000'],
                'data.certificate_no' => ['required', 'string', 'max:255'],
                'data.form' => ['required', 'integer', 'between:10,17'],
            ],
            default => [],
        };
    }

    /** @return array<int, ValidationRule|string> */
    private function yearRules(mixed $criterion): array
    {
        $criterionId = $criterion instanceof Criterion ? $criterion->getKey() : 0;

        return [
            'required',
            Rule::exists('years', 'id')
                ->where(fn (Builder $query): Builder => $query->where('status', '1')),
            Rule::exists('criterion_years', 'year_id')
                ->where(fn (Builder $query): Builder => $query->where('criterion_id', $criterionId)),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uploadResourceType.in' => 'Bu mezon uchun tanlangan resurs turiga ruxsat berilmagan.',
            'uploadResourceFile.required' => 'Yuklanadigan faylni tanlang.',
            'uploadResourceFile.mimes' => 'Faqat PDF, JPG, JPEG yoki PNG fayl yuklash mumkin.',
            'uploadResourceFile.max' => 'Fayl hajmi 2 MB dan oshmasligi kerak.',
            'uploadResourceUrl.required' => 'Resurs havolasini kiriting.',
            'year.exists' => 'Faqat ushbu mezonga biriktirilgan faol yilni tanlash mumkin.',
        ];
    }
}
