<?php

namespace App\Console\Commands;

use App\Actions\RecalculateProfessionalDevelopmentPoints;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecalculateProfessionalDevelopmentPointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:criteria:recalculate-2-1-5-points
                            {report : Qayta hisoblanadigan hisobot ID raqami}
                            {--apply : Matematik hisoblangan ballarni bazaga yozish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '2.1.5 accepted resurslarini Top reyting foizlari bo‘yicha AI siz matematik qayta hisoblaydi';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateProfessionalDevelopmentPoints $recalculatePoints): int
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
            $result = $recalculatePoints->handle($report, $apply);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Accepted', 'Matematik yangilanadigan', 'Konfliktlar'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['total'],
                $result['changes'],
                count($result['conflicts']),
            ]],
        );

        foreach ($result['sources'] as $source => $count) {
            $this->line("{$source}: {$count}");
        }

        if ($result['conflicts'] !== []) {
            $this->warn('Top oralig‘i bilan konflikti bor datum ID lar: '.implode(', ', $result['conflicts']));
        }

        if (! $apply) {
            $this->info('Bazaga o‘zgarish yozilmadi. Qo‘llash uchun --apply parametridan foydalaning.');
        }

        return self::SUCCESS;
    }
}
