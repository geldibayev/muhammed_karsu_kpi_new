<?php

namespace App\Console\Commands;

use App\Actions\RecalculatePrintedEducationalLiteraturePoints;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecalculatePrintedEducationalLiteraturePointsCommand extends Command
{
    protected $signature = 'kpi:recalculate-printed-literature-points
                            {report : Qayta hisoblanadigan hisobot ID si}
                            {--apply : Aniqlangan o\'zgarishlarni bazaga yozish}';

    protected $description = '1.2 va 1.3 accepted resurslarini Gemini ishlatmasdan sahifa va mualliflar soni bo\'yicha qayta hisoblaydi';

    public function handle(RecalculatePrintedEducationalLiteraturePoints $action): int
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
            ['Holat', 'Accepted resurslar', 'O\'zgaradigan', 'Konfliktlar', 'Aniqlanmagan'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['total'],
                $result['changes'],
                $result['conflicts'],
                count($result['unresolved_ids']),
            ]],
        );

        foreach ($result['page_sources'] as $source => $count) {
            $this->line("Sahifa manbasi {$source}: {$count}");
        }

        foreach ($result['author_sources'] as $source => $count) {
            $this->line("Muallif manbasi {$source}: {$count}");
        }

        if ($result['unresolved_ids'] !== []) {
            $this->warn('Sahifa yoki mualliflar soni aniqlanmagan datum ID lar: '.implode(', ', $result['unresolved_ids']));
        }

        if (! $apply) {
            $this->info('Gemini chaqirilmadi va bazaga o\'zgarish yozilmadi. Qo\'llash uchun --apply parametridan foydalaning.');
        }

        return self::SUCCESS;
    }
}
