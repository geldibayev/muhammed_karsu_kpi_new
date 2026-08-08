<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\Formula;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
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
                ->where('code', Formula::Maximum)
                ->first();

            if ($formula === null) {
                throw new RuntimeException('Raqobat reyting formulasi topilmadi.');
            }

            $criterion->update([
                'checking' => 'ai',
                'file_limit' => 4,
                'formula_id' => $formula->getKey(),
                'ai_submission_max_point' => 0.75,
                'divide_ai_point_by_authors' => false,
                'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
            ]);

            $criterion->criterionEvaluations()->each(function ($evaluation): void {
                $evaluation->update([
                    'has' => '1',
                    'score' => $evaluation->evaluation === 'foreign_lang' ? 2 : 3,
                ]);
            });

            CriterionManualScoreOption::query()
                ->where('criterion_id', $criterion->getKey())
                ->update(['active' => false]);
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
