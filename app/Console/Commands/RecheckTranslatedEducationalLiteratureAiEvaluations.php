<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Actions\RequeueTranslatedEducationalLiteratureEvaluations;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Report;
use Illuminate\Console\Command;
use Throwable;

class RecheckTranslatedEducationalLiteratureAiEvaluations extends Command
{
    protected $signature = 'kpi:recheck-translated-literature-ai-evaluations
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--limit= : Qayta navbatlanadigan resurslar sonini cheklash}
                            {--apply : Resurslarni checking holatiga o‘tkazib AI navbatiga qo‘yish}';

    protected $description = '1.4 mezonidagi eski tasdiqlangan resurslarni yangi tarjima va sahifa formulasi bilan qayta tekshiradi';

    public function handle(
        RequeueTranslatedEducationalLiteratureEvaluations $requeueEvaluations,
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

        $candidateCount = $requeueEvaluations->count($report);
        $plannedCount = $limit === null ? $candidateCount : min($candidateCount, $limit);
        $this->info("1.4 mezoni bo‘yicha qayta tekshiruvga mos tasdiqlangan resurslar: {$candidateCount}");

        if (! $this->option('apply')) {
            $this->warn("Dry-run: {$plannedCount} ta resurs qayta AI tekshiruviga tushadi. O‘zgarish kiritilmadi.");

            return self::SUCCESS;
        }

        $transitioned = 0;
        $queued = 0;
        $failedDispatches = 0;

        foreach ($requeueEvaluations->candidates($report)->lazyById(200) as $candidate) {
            if ($limit !== null && $transitioned >= $limit) {
                break;
            }

            $queuedDatum = $requeueEvaluations->requeue((int) $candidate->getKey(), $report);

            if ($queuedDatum === null) {
                continue;
            }

            $transitioned++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                )->afterCommit();
                $queued++;
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $queuedDatum->histories()->create([
                    'user_id' => $queuedDatum->user_id,
                    'type' => 'warning',
                    'message' => '1.4 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($transitioned > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("1.4 mezoni bo‘yicha checking holatiga o‘tkazildi: {$transitioned}");
        $this->info("AI navbatiga muvaffaqiyatli qo‘yildi: {$queued}");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatches}");

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
            $this->error('--limit musbat butun son bo‘lishi kerak.');
        }

        return $limit;
    }
}
