<?php

use App\Models\Formula;
use App\Support\LaboratoryWorkCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('criteria')->where('code', LaboratoryWorkCriterionRule::CODE)->exists()) {
            return;
        }

        $maximumFormulaId = DB::table('formulas')
            ->where('code', Formula::Maximum)
            ->value('id');

        if ($maximumFormulaId === null) {
            throw new RuntimeException('Maksimal ball formulasi topilmadi.');
        }

        DB::table('criteria')
            ->where('code', LaboratoryWorkCriterionRule::CODE)
            ->get(['id', 'desc'])
            ->each(function (object $criterion) use ($maximumFormulaId): void {
                $description = json_decode((string) $criterion->desc, true);
                $description = is_array($description) ? $description : [];
                $description['uz'] = LaboratoryWorkCriterionRule::DESCRIPTION_UZ;

                DB::table('criteria')
                    ->where('id', $criterion->id)
                    ->update([
                        'desc' => json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'formula_id' => $maximumFormulaId,
                        'file_limit' => 4,
                        'ai_submission_max_point' => LaboratoryWorkCriterionRule::BASE_POINT,
                        'divide_ai_point_by_authors' => false,
                        'ai_prompt' => LaboratoryWorkCriterionRule::PROMPT,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void {}
};
