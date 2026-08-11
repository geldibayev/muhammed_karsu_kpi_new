<?php

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Formula;
use App\Models\Report;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CRITERION_CODE = '2.1.4';

    public function up(): void
    {
        if (! Criterion::query()->where('code', self::CRITERION_CODE)->exists()) {
            return;
        }

        $formulaId = Formula::query()->where('code', Formula::Maximum)->value('id');

        if (! is_numeric($formulaId)) {
            throw new RuntimeException('maximum formulasi topilmadi.');
        }

        $reportIds = Criterion::query()
            ->where('code', self::CRITERION_CODE)
            ->pluck('report_id')
            ->filter()
            ->unique();

        DB::transaction(function () use ($formulaId): void {
            Criterion::query()
                ->where('code', self::CRITERION_CODE)
                ->lockForUpdate()
                ->update(['formula_id' => $formulaId]);
        }, 3);

        Report::query()
            ->whereKey($reportIds->all())
            ->orderBy('id')
            ->get()
            ->each(fn (Report $report): mixed => app(RecalculateReportPoints::class)->handle($report));
    }

    public function down(): void
    {
        // Forward-only domain correction: competition would reduce an approved four-point resource.
    }
};
