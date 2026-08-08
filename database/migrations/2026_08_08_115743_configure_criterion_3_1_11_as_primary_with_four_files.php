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
            ->where('code', FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE)
            ->exists()) {
            return;
        }

        $unlimitedFormulaId = DB::table('formulas')
            ->where('code', Formula::Unlimited)
            ->value('id');

        if ($unlimitedFormulaId === null) {
            throw new RuntimeException('Cheklanmagan yig‘indi formulasi topilmadi.');
        }

        DB::transaction(function () use ($unlimitedFormulaId): void {
            DB::table('criteria')
                ->where('code', FixedPerResourceHumanReviewCriterionRule::THREE_ONE_ELEVEN_CODE)
                ->orderBy('id')
                ->get(['id'])
                ->each(function (object $criterion) use ($unlimitedFormulaId): void {
                    DB::table('criteria')
                        ->where('id', $criterion->id)
                        ->update([
                            'formula_id' => $unlimitedFormulaId,
                            'file_limit' => 4,
                            'checking' => 'ai',
                            'upload' => '1',
                            'ai_submission_max_point' => 4,
                            'divide_ai_point_by_authors' => false,
                            'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::threeOneElevenPrompt(),
                            'updated_at' => now(),
                        ]);

                    foreach ([
                        'hold_degrees' => 3,
                        'no_degrees' => 4,
                        'foreign_lang' => 4,
                        'physical' => 4,
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
