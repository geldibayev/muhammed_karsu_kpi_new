<?php

use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\IndustryFundingCriterionRule;
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

            if ($reportIds->isEmpty() || ! is_numeric($maximumFormulaId)) {
                return;
            }

            $criteria = DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->whereIn('code', [IndustryFundingCriterionRule::CODE, '3.1.14'])
                ->get(['id', 'code']);

            foreach ($criteria as $criterion) {
                $attributes = [
                    'checking' => 'ai',
                    'upload' => '1',
                    'updated_at' => now(),
                ];

                if ($criterion->code === IndustryFundingCriterionRule::CODE) {
                    $attributes['ai_prompt'] = IndustryFundingCriterionRule::PROMPT;
                } else {
                    $attributes += [
                        'formula_id' => (int) $maximumFormulaId,
                        'file_limit' => 1,
                        'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::threeOneFourteenPrompt(),
                        'ai_submission_max_point' => null,
                        'divide_ai_point_by_authors' => null,
                    ];

                    $this->updateUniversityProjectEvaluationScores((int) $criterion->id);
                }

                DB::table('criteria')->where('id', $criterion->id)->update($attributes);
            }

            $this->assignPendingHumanReviews($criteria, $reviewersByCriterionCode);
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    /**
     * @param  Collection<int, object{id: int, code: string}>  $criteria
     * @param  array<string, int|string>  $reviewersByCriterionCode
     */
    private function assignPendingHumanReviews(Collection $criteria, array $reviewersByCriterionCode): void
    {
        foreach ($criteria as $criterion) {
            $reviewerHemisId = (int) ($reviewersByCriterionCode[$criterion->code] ?? 0);

            if ($reviewerHemisId <= 0) {
                continue;
            }

            DB::table('data')
                ->where('criterion_id', $criterion->id)
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
    }

    private function updateUniversityProjectEvaluationScores(int $criterionId): void
    {
        foreach ([
            'hold_degrees' => 4,
            'no_degrees' => 1,
            'foreign_lang' => 1,
            'physical' => 1,
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
                    'created_at' => now(),
                ],
            );
        }
    }
};
