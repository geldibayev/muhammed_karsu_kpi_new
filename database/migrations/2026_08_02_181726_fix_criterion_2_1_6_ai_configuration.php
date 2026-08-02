<?php

use App\Support\InternationalCooperationCriterionRule;
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
            $criterionIds = DB::table('criteria')
                ->where('code', InternationalCooperationCriterionRule::CODE)
                ->pluck('id');

            if ($criterionIds->isEmpty()) {
                return;
            }

            DB::table('criteria')
                ->whereIn('id', $criterionIds)
                ->update([
                    'ai_prompt' => InternationalCooperationCriterionRule::PROMPT,
                    'file_limit' => 1,
                    'res_type' => 'file',
                    'divide_ai_point_by_authors' => false,
                    'updated_at' => now(),
                ]);

            $scores = [
                'hold_degrees' => 3,
                'no_degrees' => 3,
                'foreign_lang' => 4,
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
