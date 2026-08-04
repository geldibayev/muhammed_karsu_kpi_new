<?php

use App\Support\MasterClassCriterionRule;
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
            $maximumFormulaId = DB::table('formulas')->where('code', 'maximum')->value('id');

            if (! is_numeric($maximumFormulaId)) {
                return;
            }

            $criterionIds = DB::table('criteria')
                ->where('code', MasterClassCriterionRule::CODE)
                ->pluck('id');

            DB::table('criteria')
                ->whereIn('id', $criterionIds)
                ->update([
                    'formula_id' => (int) $maximumFormulaId,
                    'file_limit' => 1,
                    'checking' => 'ai',
                    'upload' => '1',
                    'ai_prompt' => MasterClassCriterionRule::PROMPT,
                    'updated_at' => now(),
                ]);

            $scores = [
                'hold_degrees' => 2,
                'no_degrees' => 2,
                'foreign_lang' => 3,
                'physical' => 4,
            ];

            foreach ($criterionIds as $criterionId) {
                foreach ($scores as $evaluation => $score) {
                    DB::table('criterion_evaluations')->updateOrInsert(
                        [
                            'criterion_id' => $criterionId,
                            'evaluation' => $evaluation,
                        ],
                        [
                            'has' => '1',
                            'score' => $score,
                            'created_at' => now(),
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
    public function down(): void
    {
        $competitionFormulaId = DB::table('formulas')->where('code', 'competition')->value('id');

        if (! is_numeric($competitionFormulaId)) {
            return;
        }

        DB::table('criteria')
            ->where('code', MasterClassCriterionRule::CODE)
            ->update([
                'formula_id' => (int) $competitionFormulaId,
                'updated_at' => now(),
            ]);
    }
};
