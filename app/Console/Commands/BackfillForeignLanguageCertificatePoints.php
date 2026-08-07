<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Models\Criterion;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\Report;
use App\Support\ForeignLanguageCertificateCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillForeignLanguageCertificatePoints extends Command
{
    protected $signature = 'kpi:criteria:backfill-foreign-language-points
                            {--report= : Hisobot ID; ko‘rsatilmasa faol hisobot olinadi}
                            {--dry-run : Bazaga yozmasdan o‘zgarishlarni ko‘rsatish}';

    protected $description = '2.1.3 dagi tasdiqlangan sertifikat ballarini kafedra, daraja va ilmiy toifa bo‘yicha qayta hisoblaydi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $report = $this->report();

        if (! $report instanceof Report) {
            $this->error('Qayta hisoblash uchun hisobot topilmadi.');

            return self::FAILURE;
        }

        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', ForeignLanguageCertificateCriterionRule::CODE)
            ->first();

        if (! $criterion instanceof Criterion) {
            $this->error("Hisobotda 2.1.3 kriteriyasi topilmadi: {$report->getKey()}.");

            return self::FAILURE;
        }

        $options = CriterionManualScoreOption::query()
            ->whereBelongsTo($criterion)
            ->whereIn('code', array_keys(ForeignLanguageCertificateCriterionRule::LEVEL_LABELS))
            ->get()
            ->keyBy('code');

        if ($options->count() !== count(ForeignLanguageCertificateCriterionRule::LEVEL_LABELS)) {
            $this->error('2.1.3 sertifikat darajalari to‘liq sozlanmagan. Avval migratsiyalarni ishga tushiring.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $unresolved = 0;
        $duplicateUsers = Datum::query()
            ->where('criterion_id', $criterion->getKey())
            ->where('status', 'accepted')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id')
            ->count();

        Datum::query()
            ->where('criterion_id', $criterion->getKey())
            ->where('status', 'accepted')
            ->with([
                'manualScoreOption:id,code',
                'user:id,degree',
                'user.ratingWorkplace.department:id,parent_id',
                'histories:id,datum_id,message',
            ])
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Datum $datum) use ($options, $dryRun, &$changed, &$unresolved): void {
                $level = $this->certificateLevel($datum);

                if ($level === null || ! $options->has($level)) {
                    $unresolved++;

                    return;
                }

                $targetPoint = $this->targetPoint($datum, $level);
                $targetOptionId = $options->get($level)?->getKey();

                if ($targetPoint === null || $targetOptionId === null) {
                    $unresolved++;

                    return;
                }

                if (abs($datum->point - $targetPoint) < 0.00005
                    && $datum->manual_score_option_id === $targetOptionId) {
                    return;
                }

                $changed++;

                if (! $dryRun) {
                    $this->updateDatum(
                        $datum->getKey(),
                        $datum->criterion_id,
                        $level,
                        $targetOptionId,
                    );
                }
            });

        if (! $dryRun) {
            $recalculateReportPoints->handle($report);
        }

        $label = $dryRun ? 'O‘zgartiriladigan' : 'O‘zgartirilgan';
        $this->info("{$label} accepted resurslar: {$changed}");
        $this->warn("Darajasi aniqlanmagan resurslar: {$unresolved}");
        $this->warn("Bir nechta accepted resursi bor foydalanuvchilar: {$duplicateUsers}");

        return self::SUCCESS;
    }

    private function report(): ?Report
    {
        $reportId = trim((string) $this->option('report'));

        return Report::query()
            ->when(
                $reportId !== '',
                fn ($query) => $query->whereKey($reportId),
                fn ($query) => $query->where('status', '1')->latest('id'),
            )
            ->first();
    }

    private function certificateLevel(Datum $datum): ?string
    {
        if ($datum->manualScoreOption?->code !== null) {
            return $datum->manualScoreOption->code;
        }

        $messages = collect([$datum->reason])
            ->merge($datum->histories->sortByDesc('id')->pluck('message'));

        foreach ($messages as $message) {
            $level = ForeignLanguageCertificateCriterionRule::levelFromHistory((string) $message);

            if ($level !== null) {
                return $level;
            }
        }

        return ForeignLanguageCertificateCriterionRule::levelFromLegacyPoint($datum->point);
    }

    private function targetPoint(Datum $datum, string $level): ?float
    {
        $department = $datum->user?->ratingWorkplace?->department;

        return ForeignLanguageCertificateCriterionRule::pointFor(
            $level,
            (string) $datum->user?->degree,
            $department?->getKey(),
            $department?->parent_id,
        );
    }

    private function updateDatum(int $datumId, int $criterionId, string $level, int $optionId): void
    {
        DB::transaction(function () use ($datumId, $criterionId, $level, $optionId): void {
            $datum = Datum::query()
                ->with(['user.ratingWorkplace.department'])
                ->lockForUpdate()
                ->find($datumId);
            $optionExists = CriterionManualScoreOption::query()
                ->whereKey($optionId)
                ->where('criterion_id', $criterionId)
                ->where('code', $level)
                ->where('active', true)
                ->lockForUpdate()
                ->exists();
            $targetPoint = $datum instanceof Datum
                && $datum->status === 'accepted'
                && $datum->criterion_id === $criterionId
                && $optionExists
                ? $this->targetPoint($datum, $level)
                : null;

            if (! $datum instanceof Datum || $targetPoint === null) {
                return;
            }

            $oldPoint = $datum->point;
            $datum->update([
                'point' => $targetPoint,
                'manual_score_option_id' => $optionId,
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '2.1.3 sertifikat balli server qoidasi bo‘yicha qayta hisoblandi. '
                    .'Daraja: '.mb_strtoupper($level).'. '
                    .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                    .'Yangi ball: '.number_format($targetPoint, 4, '.', '').'.',
                'message_type' => 'foreign_language_point_recalculated',
            ]);
        }, 3);
    }
}
