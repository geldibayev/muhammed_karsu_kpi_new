<?php

namespace App\Http\Requests;

use App\Models\Criterion;
use App\Models\Datum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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

        if ($this->filled('replacement_datum_id')) {
            $datum = Datum::query()->find($this->integer('replacement_datum_id'));

            return $criterion instanceof Criterion
                && $datum?->criterion_id === $criterion->getKey()
                && $this->user()?->can('replaceFourOneOneReference', $datum) === true;
        }

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
        $maximumFileSizeMb = max(1, (int) config('kpi.upload_max_file_size_mb', 5));
        $allowedResourceTypes = $criterion instanceof Criterion
            ? $criterion->allowedSubmissionResourceTypes()
            : ['file', 'url'];
        $allowsDoiWithoutFile = $criterion instanceof Criterion
            && $criterion->usesPublicationTierAiHumanReviewScore();

        if ($criterion instanceof Criterion && $criterion->usesHIndexSubmission()) {
            return [
                'uploadResourceType' => ['required', Rule::in(['h_index'])],
                'year' => $this->yearRules($criterion),
                'h_index' => ['required', 'array'],
                'h_index.scopus' => ['nullable', 'array:link,value'],
                'h_index.scopus.link' => ['nullable', 'required_with:h_index.scopus.value', 'url:http,https', 'max:255'],
                'h_index.scopus.value' => ['nullable', 'required_with:h_index.scopus.link', 'integer', 'min:0'],
                'h_index.web_of_science' => ['nullable', 'array:link,value'],
                'h_index.web_of_science.link' => ['nullable', 'required_with:h_index.web_of_science.value', 'url:http,https', 'max:255'],
                'h_index.web_of_science.value' => ['nullable', 'required_with:h_index.web_of_science.link', 'integer', 'min:0'],
                'h_index.research_gate' => ['nullable', 'array:link,value'],
                'h_index.research_gate.link' => ['nullable', 'required_with:h_index.research_gate.value', 'url:http,https', 'max:255'],
                'h_index.research_gate.value' => ['nullable', 'required_with:h_index.research_gate.link', 'integer', 'min:0'],
            ];
        }

        $rules = [
            'replacement_datum_id' => ['nullable', 'integer', 'exists:data,id'],
            'uploadResourceType' => ['required', Rule::in($allowedResourceTypes)],
            'year' => $this->yearRules($criterion),
            'uploadResourceFile' => [
                'nullable',
                Rule::requiredIf(
                    $this->input('uploadResourceType') === 'file'
                    && (! $allowsDoiWithoutFile || ! $this->filled('article.doi')),
                ),
                Rule::prohibitedIf($this->input('uploadResourceType') !== 'file'),
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max($maximumFileSizeMb * 1024),
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
            'article.doi' => [
                'nullable',
                'string',
                'max:255',
                'regex:~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)?10\.\d{4,9}/\S+$~iu',
            ],
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
     * @return array<string, callable>
     */
    public function after(): array
    {
        if (! $this->route('upload') instanceof Criterion || ! $this->route('upload')->usesHIndexSubmission()) {
            return [];
        }

        return [
            function (Validator $validator): void {
                $profiles = ['scopus', 'web_of_science', 'research_gate'];
                $hasCompleteProfile = false;

                foreach ($profiles as $profile) {
                    $link = (string) $this->input("h_index.$profile.link");
                    $value = (string) $this->input("h_index.$profile.value");

                    if (trim($link) !== '' && trim($value) !== '') {
                        $hasCompleteProfile = true;

                        break;
                    }
                }

                if (! $hasCompleteProfile) {
                    $validator->errors()->add(
                        'h_index',
                        'Kamida bitta platforma uchun h-index profili toliq kiritilishi kerak.',
                    );
                }
            },
        ];
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
            'uploadResourceFile.required' => $this->route('upload') instanceof Criterion
                && $this->route('upload')->usesPublicationTierAiHumanReviewScore()
                    ? 'Fayl yuklang yoki yaroqli DOI kiriting.'
                    : 'Yuklanadigan faylni tanlang.',
            'uploadResourceFile.mimes' => 'Faqat PDF, JPG, JPEG yoki PNG fayl yuklash mumkin.',
            'uploadResourceFile.max' => sprintf(
                'Fayl hajmi %d MB dan oshmasligi kerak.',
                max(1, (int) config('kpi.upload_max_file_size_mb', 5)),
            ),
            'uploadResourceUrl.required' => 'Resurs havolasini kiriting.',
            'article.doi.regex' => 'DOI 10.xxxx/... yoki https://doi.org/10.xxxx/... formatida bo‘lishi kerak.',
            'year.exists' => 'Faqat ushbu mezonga biriktirilgan faol yilni tanlash mumkin.',
        ];
    }
}
