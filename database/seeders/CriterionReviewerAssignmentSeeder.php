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
        $reviewersByCriterionCode = array_map('intval', config('kpi.criterion_reviewers', []));

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
