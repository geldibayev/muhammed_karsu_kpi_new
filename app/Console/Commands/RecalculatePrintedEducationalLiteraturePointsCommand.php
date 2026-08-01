<?php

namespace App\Console\Commands;

use App\Actions\RecalculatePrintedEducationalLiteraturePoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecalculatePrintedEducationalLiteraturePointsCommand extends Command
{
    protected $signature = 'kpi:recalculate-printed-literature-points
                            {report : Qayta hisoblanadigan hisobot ID si}
                            {--apply : Aniqlangan o\'zgarishlarni bazaga yozish}
                            {--requeue-unresolved : Aniqlanmagan accepted resurslarni qayta AI tekshiruviga yuborish}';

    protected $description = '1.2 va 1.3 resurslarini qayta hisoblaydi va aniqlanmaganlarini ixtiyoriy ravishda AI navbatiga qaytaradi';

    public function handle(RecalculatePrintedEducationalLiteraturePoints $action): int
    {
        $report = Report::query()->find($this->argument('report'));

        if ($report === null) {
            $this->error('Hisobot topilmadi.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $requeueUnresolved = (bool) $this->option('requeue-unresolved');

        if ($requeueUnresolved && ! $apply) {
            $this->error('--requeue-unresolved faqat --apply bilan ishlatiladi.');

            return self::FAILURE;
        }

        try {
            $result = $action->handle($report, $apply, $requeueUnresolved);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Accepted resurslar', 'O\'zgaradigan', 'AI navbatiga qaytarildi', 'Konfliktlar', 'Aniqlanmagan'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['total'],
                $result['changes'],
                count($result['requeued_ids']),
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

        $failedDispatches = 0;

        foreach ($result['requeued_ids'] as $datumId) {
            $datum = Datum::query()->select(['id', 'user_id', 'criterion_id'])->find($datumId);

            if ($datum === null) {
                continue;
            }

            try {
                ProcessAiDatumEvaluation::dispatch($datum->getKey(), $datum->criterion_id);
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'warning',
                    'message' => 'Resursni qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if (! $apply) {
            $this->info('Gemini chaqirilmadi va bazaga o\'zgarish yozilmadi. Qo\'llash uchun --apply parametridan foydalaning.');
        }

        return $failedDispatches === 0 ? self::SUCCESS : self::FAILURE;
    }
}
