<?php

namespace App\Services;

use App\Data\AiEvaluationResult;
use App\Models\Datum;
use Illuminate\Support\Carbon;
use UnexpectedValueException;

class AiResourceDatePolicy
{
    public function enforce(Datum $datum, AiEvaluationResult $result): AiEvaluationResult
    {
        if ($result->resourceDate === null) {
            return $result->status === 'accepted'
                ? AiEvaluationResult::checking('Resurs sanasi aniq topilmadi. Sana hujjatda ko‘rsatilishi kerak.')
                : $result;
        }

        if ($datum->criterion?->isPrintedEducationalLiteratureCriterion()) {
            return $this->enforcePrintedEducationalLiteratureYear($result);
        }

        $periodStart = $this->periodStart();
        $periodEnd = $this->periodEnd();

        if (preg_match('/^\d{4}$/', $result->resourceDate) === 1) {
            return $result->status === 'accepted'
                ? AiEvaluationResult::checking(
                    "Resursning faqat yili aniqlandi. {$periodStart->format('d.m.Y')}–{$periodEnd->format('d.m.Y')} oralig‘ini tekshirish uchun to‘liq sana zarur.",
                )
                : $result;
        }

        $resourceDate = Carbon::createFromFormat('!Y-m-d', $result->resourceDate);

        if ($resourceDate->lessThan($periodStart) || $resourceDate->greaterThan($periodEnd)) {
            $reason = "Resurs sanasi ({$result->resourceDate}) ruxsat etilgan {$periodStart->format('d.m.Y')}–{$periodEnd->format('d.m.Y')} davridan tashqarida.";

            return new AiEvaluationResult(
                status: 'cancelled',
                point: 0,
                reason: $reason,
                resourceDate: $result->resourceDate,
            );
        }

        return $result;
    }

    public function periodStart(): Carbon
    {
        return $this->configuredDate('kpi.report_period_start');
    }

    public function periodEnd(): Carbon
    {
        $periodEnd = $this->configuredDate('kpi.report_period_end');

        if ($this->periodStart()->greaterThan($periodEnd)) {
            throw new UnexpectedValueException('KPI hisobot davri chegaralari noto‘g‘ri sozlangan.');
        }

        return $periodEnd;
    }

    private function enforcePrintedEducationalLiteratureYear(AiEvaluationResult $result): AiEvaluationResult
    {
        $resourceYear = (int) substr((string) $result->resourceDate, 0, 4);
        $startYear = $this->periodStart()->year;
        $endYear = $this->periodEnd()->year;

        if ($resourceYear < $startYear || $resourceYear > $endYear) {
            return new AiEvaluationResult(
                status: 'cancelled',
                point: 0,
                reason: "Chop etilgan o‘quv adabiyotining nashr yili ({$resourceYear}) ruxsat etilgan {$startYear} yoki {$endYear} yilga mos emas.",
                resourceDate: $result->resourceDate,
            );
        }

        return $result;
    }

    private function configuredDate(string $key): Carbon
    {
        $value = config($key);

        if (! is_string($value)) {
            throw new UnexpectedValueException("{$key} sozlamasi topilmadi.");
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new UnexpectedValueException("{$key} sozlamasi YYYY-MM-DD formatiga mos emas.");
        }

        return $date;
    }
}
