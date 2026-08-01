<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\Formula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OavCriterionRuleSeeder extends Seeder
{
    private const CRITERION_CODE = '4.1.1';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $criterion = $this->criterion();
            $formula = Formula::query()
                ->where('code', Formula::Competition)
                ->first();

            if ($formula === null) {
                throw new RuntimeException('Raqobat reyting formulasi topilmadi.');
            }

            $criterion->update([
                'checking' => 'manual',
                'file_limit' => 4,
                'formula_id' => $formula->getKey(),
            ]);

            $criterion->criterionEvaluations()->each(function ($evaluation): void {
                $evaluation->update([
                    'has' => '1',
                    'score' => $evaluation->evaluation === 'foreign_lang' ? 2 : 3,
                ]);
            });

            CriterionManualScoreOption::query()
                ->where('criterion_id', $criterion->getKey())
                ->where('code', '!=', 'approved_resource')
                ->update(['active' => false]);

            CriterionManualScoreOption::query()->updateOrCreate(
                [
                    'criterion_id' => $criterion->getKey(),
                    'code' => 'approved_resource',
                ],
                [
                    'label' => ['uz' => 'Tasdiqlangan resurs'],
                    'point' => 0.75,
                    'sort_order' => 1,
                    'active' => true,
                ],
            );
        }, 3);
    }

    private function criterion(): Criterion
    {
        $criterion = Criterion::query()
            ->where('code', self::CRITERION_CODE)
            ->whereHas('report', fn ($query) => $query->where('status', '1'))
            ->first();

        if ($criterion === null) {
            throw new RuntimeException('4.1.1 OAV mezoni topilmadi.');
        }

        return $criterion;
    }
}
