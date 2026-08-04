<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Actions\RequeueLegacyProfessionalDevelopmentEvaluations;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecheckProfessionalDevelopmentPoints extends Command
{
    protected $signature = 'kpi:criteria:recheck-2-1-5-points
                            {report : Qayta tekshiriladigan hisobot ID raqami}
                            {--apply : Eski resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}';

    protected $description = '2.1.5 kriteriyasidagi eski noto‘g‘ri AI ballarini yangi Top reyting foizlari bilan qayta tekshiradi';

    public function handle(
        RequeueLegacyProfessionalDevelopmentEvaluations $requeueEvaluations,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();

        if ($reportId === false) {
            $this->error('Hisobot ID musbat butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        if ($limit === false) {
            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $requeueEvaluations->count($report);
        $plannedCount = $limit === null ? $candidateCount : min($candidateCount, $limit);
        $this->info("2.1.5 bo‘yicha eski formatdagi AI resurslari: {$candidateCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn("Dry-run: {$plannedCount} ta resurs qayta tekshiruvga tushadi. O‘zgarish kiritilmadi.");

            return self::SUCCESS;
        }

        $queuedCount = 0;
        $failedDispatchCount = 0;

        foreach ($requeueEvaluations->candidates($report)->lazyById(200) as $candidate) {
            if ($limit !== null && $queuedCount >= $limit) {
                break;
            }

            $queuedDatum = $requeueEvaluations->requeue($candidate->getKey(), $report);

            if ($queuedDatum === null) {
                continue;
            }

            $queuedCount++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                )->afterCommit();
            } catch (Throwable $exception) {
                $failedDispatchCount++;
                report($exception);
                $queuedDatum->histories()->create([
                    'user_id' => $queuedDatum->user_id,
                    'type' => 'warning',
                    'message' => '2.1.5 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($queuedCount > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("2.1.5 bo‘yicha AI qayta tekshiruviga qo‘yildi: {$queuedCount}");

        if ($failedDispatchCount > 0) {
            $this->warn("{$failedDispatchCount} ta resurs navbatga yuborilmadi; ular checking holatida qoldi.");
        }

        return $failedDispatchCount === 0 ? self::SUCCESS : self::FAILURE;
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
            $this->error('--limit musbat butun son bo‘lishi kerak.');
        }

        return $limit;
    }
}
