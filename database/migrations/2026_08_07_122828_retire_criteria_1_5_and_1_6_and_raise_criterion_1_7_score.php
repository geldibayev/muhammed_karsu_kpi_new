<?php

use App\Support\KpiCriterionSpecification;
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
            $retiredCriterionIds = DB::table('criteria')
                ->where('report_id', $reportId)
                ->whereIn('code', KpiCriterionSpecification::RetiredCodes)
                ->pluck('id');

            DB::table('criteria')
                ->whereIn('id', $retiredCriterionIds)
                ->update([
                    'status' => '0',
                    'upload' => '0',
                    'updated_at' => now(),
                ]);
            DB::table('criterion_reviewer_assignments')
                ->whereIn('criterion_id', $retiredCriterionIds)
                ->delete();

            $criterion = DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', '1.7')
                ->first(['id']);

            if ($criterion === null) {
                return;
            }

            foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
                DB::table('criterion_evaluations')->updateOrInsert(
                    [
                        'criterion_id' => $criterion->id,
                        'evaluation' => $evaluation,
                    ],
                    [
                        'has' => '1',
                        'score' => 10,
                        'updated_at' => now(),
                    ],
                );
            }

            DB::table('criterion_points')
                ->where('report_id', $reportId)
                ->where('criterion_id', $criterion->id)
                ->where('point', 2)
                ->update(['point' => 10, 'updated_at' => now()]);
            DB::table('points')
                ->where('report_id', $reportId)
                ->where('criterion_id', $criterion->id)
                ->where('point', 2)
                ->update(['point' => 10, 'updated_at' => now()]);
        }, 3);
    }

    public function down(): void
    {
        // Production scoring data is intentionally restored only through a forward migration.
    }
};
