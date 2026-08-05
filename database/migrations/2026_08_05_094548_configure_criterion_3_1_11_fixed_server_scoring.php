<?php

use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $unlimitedFormulaId = DB::table('formulas')
                ->where('code', 'unlimited')
                ->value('id');

            if (! is_numeric($unlimitedFormulaId)) {
                return;
            }

            $criteria = DB::table('criteria')
                ->where('code', '3.1.11')
                ->pluck('id');

            DB::table('criteria')
                ->whereIn('id', $criteria)
                ->update([
                    'formula_id' => (int) $unlimitedFormulaId,
                    'checking' => 'ai',
                    'upload' => '1',
                    'ai_submission_max_point' => 4,
                    'divide_ai_point_by_authors' => false,
                    'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::threeOneElevenPrompt(),
                    'updated_at' => now(),
                ]);

            foreach ($criteria as $criterionId) {
                foreach ([
                    'hold_degrees' => 3,
                    'no_degrees' => 4,
                    'foreign_lang' => 4,
                    'physical' => 4,
                ] as $evaluation => $score) {
                    DB::table('criterion_evaluations')->updateOrInsert(
                        [
                            'criterion_id' => $criterionId,
                            'evaluation' => $evaluation,
                        ],
                        [
                            'has' => '1',
                            'score' => $score,
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
