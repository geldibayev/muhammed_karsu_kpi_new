<?php

namespace App\Console\Commands;

use App\Actions\EnsureTranslationSubmissionIsEligible;
use App\Actions\RecalculateReportPoints;
use App\Models\Datum;
use App\Models\Report;
use App\Services\DatumResourceFingerprintGenerator;
use App\Support\TranslatedEducationalLiteratureCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AuditTranslationCriterionDuplicates extends Command
{
    protected $signature = 'kpi:translations:audit-duplicates
        {--report= : Faqat ko‘rsatilgan hisobot ID sini tekshirish}
        {--apply : Aniqlangan 1.4 resurslarini sabab va audit tarixi bilan bekor qilish}';

    protected $description = '1.2/1.3 da ball olgan resursning 1.4 ga qayta yuklangan nusxalarini aniqlaydi';

    public function handle(
        DatumResourceFingerprintGenerator $fingerprintGenerator,
        EnsureTranslationSubmissionIsEligible $eligibility,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = $this->reportId();
        if ($reportId === false) {
            return self::FAILURE;
        }

        $duplicates = Datum::query()
            ->select(['id', 'user_id', 'criterion_id', 'year_id', 'material', 'status', 'point'])
            ->with('criterion:id,code,report_id')
            ->whereIn('status', Datum::statusesCountingTowardsUploadLimit())
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('code', TranslatedEducationalLiteratureCriterionRule::CODE)
                ->when($reportId !== null, fn (Builder $query): Builder => $query->where('report_id', $reportId)))
            ->orderBy('id')
            ->get()
            ->map(function (Datum $datum) use ($fingerprintGenerator, $eligibility): ?array {
                if ($datum->criterion === null) {
                    return null;
                }

                $source = $eligibility->findPreviouslyScoredDuplicate(
                    $datum->user_id,
                    $datum->criterion,
                    $fingerprintGenerator->forDatum($datum),
                );

                return $source === null ? null : [$datum, $source];
            })
            ->filter()
            ->values();

        $this->table(
            ['1.4 resurs', 'Oldingi resurs', 'Mezon', 'Foydalanuvchi', 'Hisobot', 'Ball'],
            $duplicates->map(fn (array $pair): array => [
                $pair[0]->getKey(),
                $pair[1]->getKey(),
                $pair[1]->criterion?->code,
                $pair[0]->user_id,
                $pair[0]->criterion?->report_id,
                $pair[0]->point,
            ])->all(),
        );

        $this->line('Bekor qilinishi kerak bo‘lgan 1.4 resurslari: '.$duplicates->count());

        if (! $this->option('apply')) {
            $this->warn('Dry-run yakunlandi. Bazaga o‘zgartirish kiritilmadi. Tuzatish uchun --apply ishlating.');

            return self::SUCCESS;
        }

        $affectedReportIds = [];

        foreach ($duplicates as [$translation, $source]) {
            $changed = DB::transaction(function () use ($translation, $source): bool {
                $lockedData = Datum::query()
                    ->with('criterion:id,code,report_id')
                    ->whereIn('id', [$translation->getKey(), $source->getKey()])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $lockedTranslation = $lockedData->get($translation->getKey());
                $lockedSource = $lockedData->get($source->getKey());

                if (! $lockedTranslation instanceof Datum
                    || ! $lockedSource instanceof Datum
                    || ! in_array($lockedTranslation->status, Datum::statusesCountingTowardsUploadLimit(), true)
                    || $lockedSource->status !== 'accepted'
                    || $lockedSource->point <= 0
                    || $lockedTranslation->user_id !== $lockedSource->user_id
                    || $lockedTranslation->criterion?->code !== TranslatedEducationalLiteratureCriterionRule::CODE
                    || ! in_array($lockedSource->criterion?->code, TranslatedEducationalLiteratureCriterionRule::PREVIOUSLY_SCORED_CODES, true)
                    || $lockedTranslation->criterion?->report_id !== $lockedSource->criterion?->report_id) {
                    return false;
                }

                $sourceCode = $lockedSource->criterion->code;
                $reason = "Resurs {$sourceCode} mezonida #{$lockedSource->getKey()} yozuv orqali avval qabul qilingan va ball olgan ayni material ekanligi aniqlandi. 1.4 mezoni faqat boshqa tildan qilingan tarjima uchun; takroriy resurs bekor qilindi.";

                $lockedTranslation->update([
                    'status' => 'cancelled',
                    'point' => 0,
                    'author_count' => null,
                    'page_count' => null,
                    'reviewer_hemis_id' => null,
                    'duplicate_of_id' => $lockedSource->getKey(),
                    'reason' => $reason,
                ]);
                $lockedTranslation->histories()->create([
                    'user_id' => $lockedTranslation->user_id,
                    'type' => 'error',
                    'message' => $reason,
                    'message_type' => 'translation_duplicate_cancelled',
                ]);

                return true;
            }, 3);

            if ($changed && $translation->criterion !== null) {
                $affectedReportIds[$translation->criterion->report_id] = true;
            }
        }

        foreach (array_keys($affectedReportIds) as $affectedReportId) {
            $report = Report::query()->find($affectedReportId);

            if ($report !== null) {
                $recalculateReportPoints->handle($report);
            }
        }

        $this->info('Aniqlangan takroriy 1.4 resurslari sabab va audit tarixi bilan bekor qilindi.');

        return self::SUCCESS;
    }

    private function reportId(): int|false|null
    {
        $option = $this->option('report');

        if ($option === null || $option === '') {
            return null;
        }

        if (! ctype_digit((string) $option) || ! Report::query()->whereKey((int) $option)->exists()) {
            $this->error('Ko‘rsatilgan hisobot topilmadi.');

            return false;
        }

        return (int) $option;
    }
}
