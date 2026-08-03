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

            if ($reportIds->isEmpty()) {
                return;
            }

            $hIndexCriterionIds = $this->criterionIds($reportIds, '3.1.4');
            DB::table('criteria')->whereIn('id', $hIndexCriterionIds)->update([
                'checking' => 'site:profile:index',
                'upload' => '0',
                'file_limit' => 0,
                'updated_at' => now(),
            ]);
            $this->updateEvaluationScores($hIndexCriterionIds, [3, 2, 2, 2]);

            $scientificCouncilCriterionIds = $this->criterionIds($reportIds, '3.1.15');
            DB::table('criteria')->whereIn('id', $scientificCouncilCriterionIds)->update([
                'checking' => 'ai',
                'upload' => '1',
                'file_limit' => 1,
                'updated_at' => now(),
            ]);
            $this->updateEvaluationScores($scientificCouncilCriterionIds, [2, null, null, null]);
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $reportIds = DB::table('reports')->where('status', '1')->pluck('id');

            if ($reportIds->isEmpty()) {
                return;
            }

            $hIndexCriterionIds = $this->criterionIds($reportIds, '3.1.4');
            DB::table('criteria')->whereIn('id', $hIndexCriterionIds)->update([
                'checking' => 'ai',
                'upload' => '1',
                'file_limit' => 1,
                'updated_at' => now(),
            ]);
            $this->updateEvaluationScores($hIndexCriterionIds, [2, 3, 3, 3]);

            $scientificCouncilCriterionIds = $this->criterionIds($reportIds, '3.1.15');
            $this->updateEvaluationScores($scientificCouncilCriterionIds, [2, null, 2, 2]);
        }, 3);
    }

    /** @param  Collection<int, int>  $reportIds */
    private function criterionIds(Collection $reportIds, string $code): Collection
    {
        return DB::table('criteria')
            ->whereIn('report_id', $reportIds)
            ->where('code', $code)
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $criterionIds
     * @param  array{0: int|null, 1: int|null, 2: int|null, 3: int|null}  $scores
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
                        'has' => $score === null ? '0' : '1',
                        'score' => $score ?? 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }
};
