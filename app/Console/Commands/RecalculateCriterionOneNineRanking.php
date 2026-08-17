<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\Report;
use Illuminate\Console\Command;

class RecalculateCriterionOneNineRanking extends Command
{
    protected $signature = 'kpi:criteria:recalculate-1-9-ranking
                            {report : Hisobot ID raqami}';

    protected $description = '1.9 accepted resurslarini 1 ballga tenglab, raqobat reytingini qayta hisoblaydi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
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

        if (! Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', Criterion::RESOURCE_COUNT_COMPETITION_CODE)
            ->exists()) {
            $this->error("Hisobotda 1.9 kriteriyasi topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $recalculateReportPoints->handle($report);
        $this->info("1.9 raqobat ballari qayta hisoblandi. Hisobot: {$reportId}.");

        return self::SUCCESS;
    }
}
