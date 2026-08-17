<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Formula;
use App\Models\Observance;
use App\Models\Report;
use App\Support\KpiCriterionSpecification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KpiCriterionSpecificationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->ensureObservancesExist();

            $report = Report::query()
                ->where('status', '1')
                ->latest('id')
                ->firstOrFail();
            $formulaIds = Formula::query()
                ->whereIn('code', [
                    KpiCriterionSpecification::Competition,
                    KpiCriterionSpecification::Maximum,
                    KpiCriterionSpecification::Unlimited,
                ])
                ->pluck('id', 'code');
            $criteria = Criterion::query()
                ->whereBelongsTo($report)
                ->whereIn('code', array_keys(KpiCriterionSpecification::currentCriteria()))
                ->get()
                ->keyBy('code');

            if ($criteria->count() !== count(KpiCriterionSpecification::currentCriteria())) {
                throw new RuntimeException('PDF spetsifikatsiyasidagi 35 ta mezonning barchasi faol hisobotdan topilmadi.');
            }

            foreach (KpiCriterionSpecification::currentCriteria() as $code => $rule) {
                $criterion = $criteria->get($code);
                $formulaId = $formulaIds->get($rule['formula']);

                if (! $criterion instanceof Criterion || ! is_numeric($formulaId)) {
                    throw new RuntimeException("{$code} mezoni uchun formula topilmadi.");
                }

                $attributes = [
                    'formula_id' => (int) $formulaId,
                    'file_limit' => $rule['file_limit'],
                    'observation' => $rule['observation'],
                    'ai_submission_max_point' => $rule['ai_submission_max_point'] ?? null,
                    'divide_ai_point_by_authors' => $rule['divide_ai_point_by_authors'] ?? null,
                ];

                if (isset($rule['description_uz'])) {
                    $description = is_array($criterion->desc) ? $criterion->desc : [];
                    $description['uz'] = $rule['description_uz'];
                    $attributes['desc'] = $description;
                }

                if (isset($rule['ai_prompt'])) {
                    $attributes['ai_prompt'] = $rule['ai_prompt'];
                }

                $criterion->update($attributes);

                foreach ($rule['scores'] as $evaluation => $score) {
                    CriterionEvaluation::query()->updateOrCreate(
                        [
                            'criterion_id' => $criterion->getKey(),
                            'evaluation' => $evaluation,
                        ],
                        [
                            'has' => $score === null ? '0' : '1',
                            'score' => $score ?? 0,
                        ],
                    );
                }
            }

            $criteria->get('3.1.4')?->update([
                'checking' => 'site:profile:index',
                'upload' => '0',
            ]);
            $criteria->get('3.1.15')?->update([
                'checking' => 'ai',
                'upload' => '1',
            ]);

            CriterionReviewerAssignment::query()
                ->with('criterion:id,code')
                ->get()
                ->each(function (CriterionReviewerAssignment $assignment): void {
                    if (filled($assignment->criterion?->code)) {
                        $assignment->update(['criterion_code' => $assignment->criterion->code]);
                    }
                });

            CriterionManualScoreOption::query()
                ->whereIn('criterion_id', collect([
                    $criteria->get('3.1.6')?->getKey(),
                    $criteria->get('3.1.7')?->getKey(),
                ])->filter())
                ->whereIn('code', ['dsc_diploma', 'phd_diploma'])
                ->update(['point' => 3]);
        }, 3);
    }

    private function ensureObservancesExist(): void
    {
        $observances = [
            'previous' => [
                'uz' => 'Avvalgi o‘quv yili uchun',
                'kaa' => 'Aldıńǵı oqıw jılı ushın',
                'ru' => 'За предыдущий учебный год',
                'en' => 'For the previous academic year',
            ],
            'current_state' => [
                'uz' => 'Joriy holati bo‘yicha',
                'kaa' => 'Házirgi jaǵdayı boyınsha',
                'ru' => 'По текущему состоянию',
                'en' => 'Based on the current state',
            ],
        ];

        foreach ($observances as $code => $name) {
            Observance::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'status' => '1'],
            );
        }
    }
}
