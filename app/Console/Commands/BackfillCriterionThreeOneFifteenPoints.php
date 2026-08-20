<?php

namespace App\Console\Commands;

use App\Actions\BackfillCriterionThreeOneFifteenPoints as BackfillPoints;
use App\Actions\RecalculateReportPoints;
use App\Models\Report;
use Illuminate\Console\Command;

class BackfillCriterionThreeOneFifteenPoints extends Command
{
    protected $signature = 'kpi:criteria:backfill-3-1-15-points
                            {report : Hisobot ID raqami}
                            {--apply : O‘zgarishlarni bazaga yozish va hisobot ballarini qayta hisoblash}';

    protected $description = '3.1.15 bo‘yicha barcha tasdiqlangan resurslarni 2 ballga tenglaydi';

    public function handle(
        BackfillPoints $backfillPoints,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($reportId === false) {
            $this->error('Hisobot ID musbat butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $backfillPoints->count($report);
        $this->info("3.1.15 bo‘yicha 2 ballga yangilanadigan resurslar: {$candidateCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.');

            return self::SUCCESS;
        }

        $updatedCount = $backfillPoints->handle($report);
        $recalculateReportPoints->handle($report);
        $this->info("3.1.15 bo‘yicha 2 ballga yangilandi: {$updatedCount}");

        return self::SUCCESS;
    }
}
