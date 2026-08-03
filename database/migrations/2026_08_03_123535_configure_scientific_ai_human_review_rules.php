<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $reportIds = DB::table('reports')->where('status', '1')->pluck('id');
            $maximumFormulaId = DB::table('formulas')->where('code', 'maximum')->value('id');

            if ($reportIds->isEmpty() || ! is_numeric($maximumFormulaId)) {
                return;
            }

            DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->where('code', '3.1.2')
                ->update([
                    'formula_id' => (int) $maximumFormulaId,
                    'updated_at' => now(),
                ]);

            $criterionIds = DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->where('code', '3.1.4')
                ->pluck('id');

            DB::table('criteria')
                ->whereIn('id', $criterionIds)
                ->update([
                    'checking' => 'ai',
                    'upload' => '1',
                    'file_limit' => 1,
                    'updated_at' => now(),
                ]);

            $this->updateEvaluationScores($criterionIds, [2, 3, 3, 3]);
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $reportIds = DB::table('reports')->where('status', '1')->pluck('id');
            $competitionFormulaId = DB::table('formulas')->where('code', 'competition')->value('id');

            if ($reportIds->isEmpty()) {
                return;
            }

            if (is_numeric($competitionFormulaId)) {
                DB::table('criteria')
                    ->whereIn('report_id', $reportIds)
                    ->where('code', '3.1.2')
                    ->update([
                        'formula_id' => (int) $competitionFormulaId,
                        'updated_at' => now(),
                    ]);
            }

            $criterionIds = DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->where('code', '3.1.4')
                ->pluck('id');

            DB::table('criteria')
                ->whereIn('id', $criterionIds)
                ->update([
                    'checking' => 'site:profile:index',
                    'upload' => '0',
                    'file_limit' => 0,
                    'updated_at' => now(),
                ]);

            $this->updateEvaluationScores($criterionIds, [3, 2, 2, 2]);
        }, 3);
    }

    /**
     * @param  Collection<int, int>  $criterionIds
     * @param  array{0: int, 1: int, 2: int, 3: int}  $scores
     */
    private function updateEvaluationScores(Collection $criterionIds, array $scores): void
    {
        $scoresByEvaluation = array_combine(
            ['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'],
            $scores,
        );

        foreach ($criterionIds as $criterionId) {
            foreach ($scoresByEvaluation as $evaluation => $score) {
                DB::table('criterion_evaluations')->updateOrInsert(
                    [
                        'criterion_id' => $criterionId,
                        'evaluation' => $evaluation,
                    ],
                    [
                        'has' => '1',
                        'score' => $score,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }
};
