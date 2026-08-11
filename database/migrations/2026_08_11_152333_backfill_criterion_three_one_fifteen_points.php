<?php

use App\Actions\BackfillCriterionThreeOneFifteenPoints;
use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Report;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $reportIds = Criterion::query()
            ->where('code', '3.1.15')
            ->pluck('report_id')
            ->filter()
            ->unique();

        Report::query()
            ->whereKey($reportIds->all())
            ->orderBy('id')
            ->get()
            ->each(function (Report $report): void {
                app(BackfillCriterionThreeOneFifteenPoints::class)->handle($report);
                app(RecalculateReportPoints::class)->handle($report);
            });
    }

    public function down(): void
    {
        // Forward-only domain correction: restoring incorrect accepted points is unsafe.
    }
};
