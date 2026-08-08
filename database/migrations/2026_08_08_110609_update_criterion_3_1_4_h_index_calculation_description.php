<?php

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Report;
use App\Support\HIndexCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $reportIds = Criterion::query()
            ->where('code', HIndexCriterionRule::CODE)
            ->pluck('report_id')
            ->filter()
            ->unique();

        DB::transaction(function (): void {
            Criterion::query()
                ->where('code', HIndexCriterionRule::CODE)
                ->lockForUpdate()
                ->get()
                ->each(function (Criterion $criterion): void {
                    $description = is_array($criterion->desc) ? $criterion->desc : [];
                    $description['uz'] = HIndexCriterionRule::DESCRIPTION_UZ;
                    $criterion->update(['desc' => $description]);
                });
        }, 3);

        Report::query()
            ->whereKey($reportIds->all())
            ->get()
            ->each(fn (Report $report) => app(RecalculateReportPoints::class)->handle($report));
    }

    public function down(): void
    {
        // Forward-only domain correction: restoring the misleading summation rule is unsafe.
    }
};
