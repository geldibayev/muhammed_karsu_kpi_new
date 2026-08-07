<?php

use App\Models\Formula;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('criteria')
            ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE)
            ->exists()) {
            return;
        }

        $maximumFormulaId = DB::table('formulas')
            ->where('code', Formula::Maximum)
            ->value('id');

        if ($maximumFormulaId === null) {
            throw new RuntimeException('Maksimal ball formulasi topilmadi.');
        }

        DB::transaction(function () use ($maximumFormulaId): void {
            DB::table('criteria')
                ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_TWO_CODE)
                ->orderBy('id')
                ->get(['id'])
                ->each(function (object $criterion) use ($maximumFormulaId): void {
                    DB::table('criteria')
                        ->where('id', $criterion->id)
                        ->update([
                            'formula_id' => $maximumFormulaId,
                            'file_limit' => 1,
                            'checking' => 'ai',
                            'upload' => '1',
                            'ai_submission_max_point' => 2,
                            'divide_ai_point_by_authors' => false,
                            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneTwoPrompt(),
                            'updated_at' => now(),
                        ]);

                    foreach ([
                        'hold_degrees' => 1,
                        'no_degrees' => 1,
                        'foreign_lang' => 1,
                        'physical' => 2,
                    ] as $evaluation => $score) {
                        DB::table('criterion_evaluations')->updateOrInsert(
                            [
                                'criterion_id' => $criterion->id,
                                'evaluation' => $evaluation,
                            ],
                            [
                                'has' => '1',
                                'score' => $score,
                                'updated_at' => now(),
                            ],
                        );
                    }
                });
        }, 3);
    }

    public function down(): void
    {
        // Production scoring data is intentionally restored only through a forward migration.
    }
};
