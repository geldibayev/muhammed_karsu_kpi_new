<?php

use App\Support\ScopusCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reportId = DB::table('reports')
            ->where('status', '1')
            ->orderByDesc('id')
            ->value('id');

        if (! is_numeric($reportId)) {
            return;
        }

        DB::transaction(function () use ($reportId): void {
            $criterion = DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', ScopusCriterionRule::CODE)
                ->first(['id', 'desc']);

            if ($criterion === null) {
                return;
            }

            $description = json_decode($criterion->desc ?? '[]', true);
            $description = is_array($description) ? $description : [];
            $description['uz'] = ScopusCriterionRule::DESCRIPTION_UZ;

            DB::table('criteria')->where('id', $criterion->id)->update([
                'desc' => json_encode($description, JSON_UNESCAPED_UNICODE),
                'checking' => 'ai',
                'ai_prompt' => ScopusCriterionRule::PROMPT,
                'ai_submission_max_point' => ScopusCriterionRule::MAXIMUM_POINT,
                'divide_ai_point_by_authors' => false,
                'updated_at' => now(),
            ]);

            foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
                DB::table('criterion_evaluations')->updateOrInsert(
                    [
                        'criterion_id' => $criterion->id,
                        'evaluation' => $evaluation,
                    ],
                    [
                        'has' => '1',
                        'score' => ScopusCriterionRule::MAXIMUM_POINT,
                        'updated_at' => now(),
                    ],
                );
            }
        }, 3);
    }

    public function down(): void
    {
        // Production scoring configuration is intentionally restored only through a forward migration.
    }
};
