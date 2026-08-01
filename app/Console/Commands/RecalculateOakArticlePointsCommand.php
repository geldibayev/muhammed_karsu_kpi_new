<?php

namespace App\Console\Commands;

use App\Actions\RecalculateOakArticlePoints;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecalculateOakArticlePointsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:recalculate-oak-article-points
                            {report : Qayta hisoblanadigan hisobot ID si}
                            {--apply : O‘zgarishlarni bazaga yozish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '3.1.1 accepted resurslarini 0.5/0.75 ballni mualliflar soniga bo‘lib qayta hisoblaydi';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateOakArticlePoints $action): int
    {
        $report = Report::query()->find($this->argument('report'));

        if ($report === null) {
            $this->error('Hisobot topilmadi.');

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
            ['Holat', 'Accepted resurslar', 'O‘zgaradigan', 'Konfliktlar', 'Aniqlanmagan'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['total'],
                $result['changes'],
                $result['conflicts'],
                count($result['unmatched_ids']),
            ]],
        );

        foreach ($result['sources'] as $source => $count) {
            $this->line("{$source}: {$count}");
        }

        if ($result['unmatched_ids'] !== []) {
            $this->warn('Mualliflar soni aniqlanmagan datum ID lar: '.implode(', ', $result['unmatched_ids']));
        }

        if (! $apply) {
            $this->info('Bazaga o‘zgarish yozilmadi. Qo‘llash uchun --apply parametridan foydalaning.');
        }

        return self::SUCCESS;
    }
}
