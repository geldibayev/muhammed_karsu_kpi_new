<?php

use App\Models\Formula;
use App\Support\OakArticleCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->configure(Formula::Maximum, true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->configure(Formula::Competition, null);
    }

    private function configure(string $formulaCode, ?bool $divideByAuthors): void
    {
        DB::transaction(function () use ($formulaCode, $divideByAuthors): void {
            $reportId = DB::table('reports')
                ->where('status', '1')
                ->orderByDesc('id')
                ->value('id');

            if (! is_numeric($reportId)) {
                return;
            }

            $criterion = DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', OakArticleCriterionRule::CODE)
                ->first(['id', 'desc']);

            if ($criterion === null) {
                return;
            }

            $formulaId = DB::table('formulas')->where('code', $formulaCode)->value('id');

            if (! is_numeric($formulaId)) {
                throw new RuntimeException("{$formulaCode} formulasi topilmadi.");
            }

            $description = json_decode($criterion->desc ?? '[]', true);
            $description = is_array($description) ? $description : [];
            $description['uz'] = OakArticleCriterionRule::DESCRIPTION_UZ;

            DB::table('criteria')->where('id', $criterion->id)->update([
                'formula_id' => $formulaId,
                'divide_ai_point_by_authors' => $divideByAuthors,
                'desc' => json_encode($description, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }, 3);
    }
};
