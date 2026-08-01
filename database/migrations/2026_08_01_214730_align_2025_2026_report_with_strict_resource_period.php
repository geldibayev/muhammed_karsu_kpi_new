<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('reports')
            ->where('code', '2025-2026')
            ->update([
                'starts_on' => config('kpi.report_period_start'),
                'ends_on' => config('kpi.report_period_end'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reports')
            ->where('code', '2025-2026')
            ->where('starts_on', '2025-09-01')
            ->where('ends_on', '2026-08-31')
            ->update([
                'ends_on' => '2026-07-31',
                'updated_at' => now(),
            ]);
    }
};
