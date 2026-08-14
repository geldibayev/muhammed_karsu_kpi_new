<?php

namespace App\Console\Commands;

use App\Actions\EnforceOakArticleFileLimit;
use App\Actions\RecalculateReportPoints;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class EnforceOakArticleFileLimitCommand extends Command
{
    protected $signature = 'kpi:criteria:enforce-3-1-1-file-limit
                            {report : Hisobot ID raqami}
                            {--apply : Ortiqcha accepted resurslarni rad etish va hisobot ballarini qayta hisoblash}';

    protected $description = '3.1.1 bo‘yicha eng katta balli 4 ta accepted resursni qoldirib, ortiqchalarini rad etadi';

    public function handle(
        EnforceOakArticleFileLimit $enforceFileLimit,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($reportId === false || ($report = Report::query()->find($reportId)) === null) {
            $this->error('Hisobot topilmadi yoki ID noto‘g‘ri.');

            return self::FAILURE;
        }

        try {
            $result = (bool) $this->option('apply')
                ? $enforceFileLimit->handle($report)
                : $enforceFileLimit->analyse($report);

            if ((bool) $this->option('apply') && $result['excess'] > 0) {
                $recalculateReportPoints->handle($report);
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Accepted resurslar', 'Ortiqcha resurs egalari', 'Rad etiladigan resurslar'],
            [[
                (bool) $this->option('apply') ? 'APPLIED' : 'DRY RUN',
                $result['accepted'],
                $result['affected_users'],
                $result['excess'],
            ]],
        );

        if (! (bool) $this->option('apply')) {
            $this->warn('Bazaga o‘zgarish yozilmadi. Qo‘llash uchun --apply parametridan foydalaning.');
        }

        return self::SUCCESS;
    }
}
