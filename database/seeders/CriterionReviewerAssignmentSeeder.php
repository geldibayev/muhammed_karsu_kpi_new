<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use Illuminate\Database\Seeder;
use RuntimeException;

class CriterionReviewerAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $settingsManagerHemisId = (int) config('kpi.settings_manager_hemis_id');
        $oavReviewerHemisId = (int) config('kpi.ai_status_viewer_hemis_id');

        if ($settingsManagerHemisId <= 0 || $oavReviewerHemisId <= 0) {
            throw new RuntimeException('KPI mas’ullarining HEMIS ID sozlamalari to‘liq emas.');
        }

        $reviewersByCriterionCode = array_map('intval', config('kpi.criterion_reviewers', []));
        $reviewersByCriterionCode += [
            '2.1.4' => $settingsManagerHemisId,
            '4.1.1' => $oavReviewerHemisId,
        ];

        if (in_array(0, $reviewersByCriterionCode, true)) {
            throw new RuntimeException('Mezon mas’ullarining HEMIS ID sozlamalarida noto‘g‘ri qiymat bor.');
        }
        $criteria = Criterion::query()
            ->whereHas('report', fn ($query) => $query->where('status', '1'))
            ->whereIn('code', array_keys($reviewersByCriterionCode))
            ->get(['id', 'code'])
            ->keyBy('code');

        foreach ($reviewersByCriterionCode as $criterionCode => $hemisId) {
            $criterion = $criteria->get($criterionCode);

            if (! $criterion instanceof Criterion) {
                throw new RuntimeException("{$criterionCode} mezoni topilmadi.");
            }

            CriterionReviewerAssignment::query()->updateOrCreate(
                ['criterion_id' => $criterion->getKey()],
                [
                    'hemis_id' => $hemisId,
                    'criterion_code' => $criterionCode,
                ],
            );
        }
    }
}
