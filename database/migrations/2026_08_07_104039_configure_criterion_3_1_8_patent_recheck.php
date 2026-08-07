<?php

use App\Support\PatentCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('criteria')
                ->where('code', PatentCriterionRule::CODE)
                ->orderBy('id')
                ->get(['id', 'desc'])
                ->each(function (object $criterion): void {
                    $description = is_string($criterion->desc)
                        ? json_decode($criterion->desc, true)
                        : [];
                    $description = is_array($description) ? $description : [];
                    $description = [...$description, ...PatentCriterionRule::descriptions()];

                    DB::table('criteria')
                        ->where('id', $criterion->id)
                        ->update([
                            'desc' => json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'checking' => 'ai',
                            'upload' => '1',
                            'ai_submission_max_point' => 4,
                            'divide_ai_point_by_authors' => false,
                            'ai_prompt' => PatentCriterionRule::PROMPT,
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
        // Production evaluation rules are restored only through a forward migration.
    }
};
