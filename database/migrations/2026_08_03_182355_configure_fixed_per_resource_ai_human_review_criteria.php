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
            $reviewersByCriterionCode = config('kpi.ai_human_review_criterion_reviewers', []);
            $reviewerHemisId = (int) ($reviewersByCriterionCode['3.1.12'] ?? 0);

            if ($reportIds->isEmpty() || ! is_numeric($maximumFormulaId) || $reviewerHemisId <= 0) {
                return;
            }

            $rules = [
                '3.1.12' => ['file_limit' => 1, 'scores' => [3, 3, 3, 3]],
                '4.1.3' => ['file_limit' => 4, 'scores' => [2, 3, 1, 3]],
                '4.1.4' => ['file_limit' => 4, 'scores' => [2, 3, 2, 4]],
                '4.1.5' => ['file_limit' => 2, 'scores' => [2, 1, 2, 2]],
            ];
            $criteria = DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->whereIn('code', array_keys($rules))
                ->get(['id', 'code']);

            foreach ($criteria as $criterion) {
                $rule = $rules[$criterion->code];

                DB::table('criteria')->where('id', $criterion->id)->update([
                    'formula_id' => (int) $maximumFormulaId,
                    'file_limit' => $rule['file_limit'],
                    'checking' => 'ai',
                    'upload' => '1',
                    'updated_at' => now(),
                ]);

                $this->updateEvaluationScores(collect([$criterion->id]), $rule['scores']);
            }

            $this->assignPendingHumanReviews($criteria->pluck('id'), $reviewerHemisId);
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

            if ($reportIds->isEmpty() || ! is_numeric($competitionFormulaId)) {
                return;
            }

            DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->whereIn('code', ['4.1.3', '4.1.4', '4.1.5'])
                ->update([
                    'formula_id' => (int) $competitionFormulaId,
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    /** @param Collection<int, int> $criterionIds */
    private function assignPendingHumanReviews(Collection $criterionIds, int $reviewerHemisId): void
    {
        DB::table('data')
            ->whereIn('criterion_id', $criterionIds)
            ->where('status', 'checking')
            ->where(function ($query) use ($reviewerHemisId): void {
                $query->whereNull('reviewer_hemis_id')
                    ->orWhere('reviewer_hemis_id', '!=', $reviewerHemisId);
            })
            ->whereRaw("(SELECT COALESCE(MAX(ai_history.id), 0) FROM datum_histories ai_history WHERE ai_history.datum_id = data.id AND ai_history.message_type = 'ai_evaluation') > (SELECT COALESCE(MAX(transfer_history.id), 0) FROM datum_histories transfer_history WHERE transfer_history.datum_id = data.id AND transfer_history.message_type = 'criterion_transferred')")
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->each(function (object $datum) use ($reviewerHemisId): void {
                DB::table('data')->where('id', $datum->id)->update([
                    'reviewer_hemis_id' => $reviewerHemisId,
                    'updated_at' => now(),
                ]);
                DB::table('datum_histories')->insert([
                    'datum_id' => $datum->id,
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => "AI inson tekshiruvi HEMIS ID {$reviewerHemisId} mas’ulga biriktirildi.",
                    'message_type' => 'ai_human_review_assigned',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
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
