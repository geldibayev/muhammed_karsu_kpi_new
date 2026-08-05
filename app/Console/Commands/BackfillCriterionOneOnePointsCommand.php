<?php

namespace App\Console\Commands;

use App\Actions\BackfillCriterionOneOnePoints;
use App\Actions\RecalculateReportPoints;
use App\Models\Report;
use Illuminate\Console\Command;

class BackfillCriterionOneOnePointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:criteria:recalculate-1-1-points
                            {report : Hisobot ID raqami}
                            {--apply : Ballarni bazaga yozish va hisobot reytingini qayta hisoblash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '1.1 accepted resurslarini turi va foydalanuvchi toifasi bo‘yicha 50/40/10 foizlarda qayta hisoblaydi';

    /**
     * Execute the console command.
     */
    public function handle(
        BackfillCriterionOneOnePoints $backfill,
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

        $preview = $backfill->preview($report);
        $this->info("1.1 bo‘yicha accepted resurslar: {$preview['total']}");
        $this->info("Yangilanishi kerak: {$preview['changed']}");
        $this->info('Aniqlanmagan resurslar: '.count($preview['unresolved_ids']));

        if ($preview['unresolved_ids'] !== []) {
            $this->error('Resurs turi aniqlanmagan ID lar: '.implode(', ', $preview['unresolved_ids']));

            if ((bool) $this->option('apply')) {
                $this->error('Xavfsizlik uchun hech qanday o‘zgarish yozilmadi. Avval aniqlanmagan resurslarni tekshiring.');

                return self::FAILURE;
            }
        }

        if (! (bool) $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi. Yozish uchun --apply parametridan foydalaning.');

            return self::SUCCESS;
        }

        $result = $backfill->handle($report);

        if ($result['unresolved_ids'] !== []) {
            $this->error('Qayta tekshiruv vaqtida ayrim resurslar aniqlanmadi; hech qanday o‘zgarish yozilmadi.');

            return self::FAILURE;
        }

        $recalculateReportPoints->handle($report);
        $this->info("1.1 bo‘yicha yangilandi: {$result['changed']}");
        $this->info("O‘zgarishsiz qoldi: {$result['unchanged']}");

        return self::SUCCESS;
    }
}
