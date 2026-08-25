<?php

namespace App\Console\Commands;

use App\Actions\ApplyTeachingQualityScoreSnapshot;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class ApplyTeachingQualityScoreSnapshotCommand extends Command
{
    protected $signature = 'kpi:criteria:apply-teaching-quality-snapshot
                            {report : 2025-2026 hisobotining ID raqami}
                            {--apply : Ballarni bazaga yozish va hisobotni qayta hisoblash}
                            {--show-missing : Tizimda topilmagan HEMIS IDlarni chiqarish}';

    protected $description = 'Kodga kiritilgan anketa snapshotidan 1.5 o‘qitish sifati ballarini HEMIS ID bo‘yicha qo‘llaydi';

    public function handle(ApplyTeachingQualityScoreSnapshot $action): int
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

        $apply = (bool) $this->option('apply');

        try {
            $result = $action->handle($report, $apply);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Snapshot', 'Mos user', 'Topilmadi', 'Yangi', 'Yangilanadi', 'O‘zgarmaydi', 'Chiqariladi', 'Konflikt'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['rows'],
                $result['matched_users'],
                $result['missing_users'],
                $result['created'],
                $result['updated'],
                $result['unchanged'],
                $result['removed'],
                $result['conflicts'],
            ]],
        );

        if ($result['conflicts'] > 0) {
            $this->warn('1.5 mezonida boshqa resurslar bor. --apply ularni avtomatik o‘zgartirmaydi.');
        }

        if ((bool) $this->option('show-missing') && $result['missing_hemis_ids'] !== []) {
            $this->newLine();
            $this->warn('Tizimda topilmagan HEMIS IDlar:');

            foreach (array_chunk($result['missing_hemis_ids'], 20) as $hemisIds) {
                $this->line(implode(', ', $hemisIds));
            }
        }

        if (! $apply) {
            $this->warn('Bazaga yozilmadi. Natija to‘g‘ri bo‘lsa --apply bilan qayta ishga tushiring.');
        }

        return self::SUCCESS;
    }
}
