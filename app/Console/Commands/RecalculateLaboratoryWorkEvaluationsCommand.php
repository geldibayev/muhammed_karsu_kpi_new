<?php

namespace App\Console\Commands;

use App\Actions\RecalculateLaboratoryWorkEvaluations;
use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecalculateLaboratoryWorkEvaluationsCommand extends Command
{
    protected $signature = 'kpi:recalculate-criterion-1-6
                            {report : Qayta hisoblanadigan hisobot IDsi}
                            {--limit= : O\'zgartiriladigan resurslar sonini cheklash}
                            {--apply : Ballarni yangilash va noaniq resurslarni AI navbatiga qo\'yish}';

    protected $description = '1.6 mezonidagi eski tasdiqlangan resurslar ballini mualliflar soni bo\'yicha yangilaydi';

    public function handle(
        RecalculateLaboratoryWorkEvaluations $evaluations,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();

        if ($reportId === false || $limit === false) {
            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        try {
            $analysis = $evaluations->analyse($report, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("1.6 tasdiqlangan resurslari: {$analysis['total']}");
        $this->info("Qayta hisoblanadi: {$analysis['recalculations']}; AI tekshiruviga yuboriladi: {$analysis['rechecks']}; o'zgarishsiz: {$analysis['unchanged']}.");

        if (! $this->option('apply')) {
            $this->warn('Dry-run: ma\'lumotlar o\'zgartirilmadi. Amalga oshirish uchun --apply ishlating.');

            return self::SUCCESS;
        }

        $changed = 0;
        $recalculated = 0;
        $rechecked = 0;
        $queued = 0;
        $failedDispatches = 0;

        foreach ($evaluations->candidates($report)->lazyById(200) as $candidate) {
            if ($limit !== null && $changed >= $limit) {
                break;
            }

            $result = $evaluations->process((int) $candidate->getKey(), $report);

            if ($result === null) {
                continue;
            }

            $changed++;

            if ($result['outcome'] === 'recalculated') {
                $recalculated++;

                continue;
            }

            $rechecked++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $result['datum']->getKey(),
                    $result['datum']->criterion_id,
                )->afterCommit();
                $queued++;
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $result['datum']->histories()->create([
                    'user_id' => $result['datum']->user_id,
                    'type' => 'warning',
                    'message' => '1.6 resursini AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($changed > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("Qayta hisoblandi: {$recalculated}; checking holatiga o'tkazildi: {$rechecked}; AI navbatiga qo'yildi: {$queued}.");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo'yishda xato: {$failedDispatches}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function validatedLimit(): int|false|null
    {
        $value = $this->option('limit');

        if ($value === null) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($limit === false) {
            $this->error('--limit musbat butun son bo\'lishi kerak.');
        }

        return $limit;
    }
}
