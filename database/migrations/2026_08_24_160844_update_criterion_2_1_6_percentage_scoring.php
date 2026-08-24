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
            $criteria = DB::table('criteria')
                ->where('code', InternationalCooperationCriterionRule::CODE)
                ->get(['id', 'desc']);

            foreach ($criteria as $criterion) {
                $description = json_decode((string) $criterion->desc, true);
                $description = is_array($description) ? $description : [];
                $description['uz'] = InternationalCooperationCriterionRule::DESCRIPTION_UZ;

                DB::table('criteria')
                    ->where('id', $criterion->id)
                    ->update([
                        'desc' => json_encode($description, JSON_UNESCAPED_UNICODE),
                        'ai_prompt' => InternationalCooperationCriterionRule::PROMPT,
                        'file_limit' => 1,
                        'res_type' => 'file',
                        'divide_ai_point_by_authors' => false,
                        'updated_at' => now(),
                    ]);

                foreach (['hold_degrees' => 3, 'no_degrees' => 3, 'foreign_lang' => 4, 'physical' => 4] as $evaluation => $score) {
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
            }
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
