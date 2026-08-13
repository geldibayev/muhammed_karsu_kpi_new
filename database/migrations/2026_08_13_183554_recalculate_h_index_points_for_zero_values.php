<?php

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Report;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $reportIds = Criterion::query()
            ->where('code', Criterion::H_INDEX_CODE)
            ->whereHas('files', fn ($query) => $query->where('status', 'accepted'))
            ->distinct()
            ->pluck('report_id');

        Report::query()
            ->whereKey($reportIds)
            ->each(fn (Report $report) => app(RecalculateReportPoints::class)->handle($report));
    }

    public function down(): void
    {
        // Forward-only correction: restoring incorrect H-index points is unsafe.
    }
};
