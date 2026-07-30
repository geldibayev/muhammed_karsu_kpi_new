<?php

namespace App\Console\Commands;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignFixedScoreCriterionReviewer extends Command
{
    protected $signature = 'kpi:reviewers:assign-fixed-score
        {criterion-code : Kriteriya kodi, masalan 2/16}
        {hemis-id : Mas’ulning HEMIS ID raqami}
        {point : Tasdiqlanganda beriladigan qat’iy ball}
        {--dry-run : Bazaga yozmasdan natijani ko‘rsatish}';

    protected $description = 'Kriteriyaga mas’ul biriktiradi va tasdiqlash uchun qat’iy ball sozlaydi';

    public function handle(): int
    {
        $criterionCode = trim((string) $this->argument('criterion-code'));
        $hemisId = trim((string) $this->argument('hemis-id'));
        $pointArgument = trim((string) $this->argument('point'));

        if (! preg_match('/^([1-9]\d*)\/([1-9]\d*)$/', $criterionCode, $matches)) {
            $this->error('Kriteriya kodi 2/16 ko‘rinishida bo‘lishi kerak.');

            return self::FAILURE;
        }

        if (! ctype_digit($hemisId) || (int) $hemisId <= 0) {
            $this->error('HEMIS ID musbat butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        if (! is_numeric($pointArgument) || (float) $pointArgument <= 0) {
            $this->error('Ball musbat son bo‘lishi kerak.');

            return self::FAILURE;
        }

        $point = round((float) $pointArgument, 2);

        if ($point > 999999.99) {
            $this->error('Ball bazadagi ruxsat etilgan chegaradan oshdi.');

            return self::FAILURE;
        }

        $criterion = $this->resolveCriterion(
            (int) $matches[1],
            (int) $matches[2],
        );

        if ($criterion === null) {
            $this->error("{$criterionCode} kriteriyasi topilmadi.");

            return self::FAILURE;
        }

        if ($criterion->checking !== 'manual') {
            $this->error('Kriteriyaning tekshirish turi manual emas.');

            return self::FAILURE;
        }

        $conflictingAssignment = CriterionReviewerAssignment::query()
            ->where('criterion_code', $criterionCode)
            ->where('criterion_id', '!=', $criterion->getKey())
            ->exists();

        if ($conflictingAssignment) {
            $this->error("{$criterionCode} kodi boshqa kriteriyaga biriktirilgan.");

            return self::FAILURE;
        }

        $evaluationMaximums = CriterionEvaluation::query()
            ->where('criterion_id', $criterion->getKey())
            ->where('has', '1')
            ->pluck('score');

        if ($evaluationMaximums->isEmpty()) {
            $this->error('Kriteriya uchun faol maksimal ball sozlanmagan.');

            return self::FAILURE;
        }

        if ($evaluationMaximums->contains(
            fn (mixed $maximum): bool => (float) $maximum < $point,
        )) {
            $this->error('Qat’iy ball kriteriyadagi ayrim baholash toifalari maksimumidan oshadi.');

            return self::FAILURE;
        }

        $pendingCount = Datum::query()
            ->where('criterion_id', $criterion->getKey())
            ->whereIn('status', [
                DatumStatus::Received->value,
                DatumStatus::Checking->value,
            ])
            ->count();
        $reviewerExists = User::query()->where('hemis_id', $hemisId)->exists();

        $this->info(sprintf(
            '%s — %s',
            $criterionCode,
            data_get($criterion->name, 'uz', 'Nomsiz kriteriya'),
        ));
        $this->line("Mas’ul HEMIS ID: {$hemisId}");
        $this->line('Tasdiqlash balli: '.number_format($point, 2, '.', ''));
        $this->line("Mas’ul navbatida ko‘rinadigan resurslar: {$pendingCount}");

        if (! $reviewerExists) {
            $this->warn('Bu HEMIS ID lokal users jadvalida topilmadi; assignment baribir saqlanishi mumkin.');
        }

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry-run: bazaga o‘zgartirish kiritilmadi.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($criterion, $criterionCode, $hemisId, $point): void {
            CriterionReviewerAssignment::query()->updateOrCreate(
                ['criterion_id' => $criterion->getKey()],
                [
                    'hemis_id' => $hemisId,
                    'criterion_code' => $criterionCode,
                ],
            );

            CriterionManualScoreOption::query()
                ->where('criterion_id', $criterion->getKey())
                ->where('code', '!=', CriterionManualScoreOption::FIXED_APPROVAL_CODE)
                ->update(['active' => false]);

            CriterionManualScoreOption::query()->updateOrCreate(
                [
                    'criterion_id' => $criterion->getKey(),
                    'code' => CriterionManualScoreOption::FIXED_APPROVAL_CODE,
                ],
                [
                    'label' => ['uz' => 'Tasdiqlansa avtomatik ball'],
                    'point' => $point,
                    'sort_order' => 0,
                    'active' => true,
                ],
            );
        }, 3);

        $this->info('Mas’ul va qat’iy ball muvaffaqiyatli sozlandi.');

        return self::SUCCESS;
    }

    private function resolveCriterion(int $sectionNumber, int $criterionId): ?Criterion
    {
        $criterion = Criterion::query()
            ->whereKey($criterionId)
            ->whereNotNull('parent_id')
            ->first();

        if ($criterion === null) {
            return null;
        }

        $actualSectionNumber = Criterion::query()
            ->where('report_id', $criterion->report_id)
            ->whereNull('parent_id')
            ->where('id', '<=', $criterion->parent_id)
            ->count();

        if ($actualSectionNumber !== $sectionNumber) {
            return null;
        }

        return $criterion;
    }
}
