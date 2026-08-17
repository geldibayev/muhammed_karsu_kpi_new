<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->withinActiveReport(function (int $reportId): void {
            $this->renumber($reportId, [
                '1.5' => 'retired.1.5',
                '1.6' => 'retired.1.6',
                '1.7' => '1.5',
                '1.8' => '1.6',
                '1.9' => '1.7',
                '1.10' => '1.8',
            ]);
        });
    }

    public function down(): void
    {
        $this->withinActiveReport(function (int $reportId): void {
            $this->renumber($reportId, [
                '1.8' => '1.10',
                '1.7' => '1.9',
                '1.6' => '1.8',
                '1.5' => '1.7',
                'retired.1.6' => '1.6',
                'retired.1.5' => '1.5',
            ]);
        });
    }

    /** @param  array<string, string>  $codes */
    private function renumber(int $reportId, array $codes): void
    {
        foreach ($codes as $from => $to) {
            $criterionIds = DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', $from)
                ->pluck('id');

            DB::table('criterion_reviewer_assignments')
                ->whereIn('criterion_id', $criterionIds)
                ->update(['criterion_code' => $to, 'updated_at' => now()]);

            $attributes = ['code' => $to, 'updated_at' => now()];

            if (is_numeric($to)) {
                $attributes['sort_order'] = (int) str($to)->afterLast('.')->toString();
            }

            DB::table('criteria')
                ->where('report_id', $reportId)
                ->where('code', $from)
                ->update($attributes);
        }
    }

    private function withinActiveReport(callable $callback): void
    {
        $reportId = DB::table('reports')
            ->where('status', '1')
            ->orderByDesc('id')
            ->value('id');

        if (! is_numeric($reportId)) {
            return;
        }

        DB::transaction(function () use ($callback, $reportId): void {
            DB::table('reports')->where('id', $reportId)->lockForUpdate()->first();
            DB::table('criteria')->where('report_id', $reportId)->lockForUpdate()->get();

            $callback((int) $reportId);
        }, 3);
    }
};
