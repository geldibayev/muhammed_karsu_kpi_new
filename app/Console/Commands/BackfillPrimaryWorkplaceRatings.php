<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Actions\ResolveUserEvaluationCategory;
use App\Actions\SyncHemisWorkplaces;
use App\Models\Report;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillPrimaryWorkplaceRatings extends Command
{
    protected $signature = 'kpi:ratings:backfill-primary-workplaces
                            {--dry-run : O‘zgarishlarni bazaga yozmasdan natijani ko‘rsatish}
                            {--sync-hemis : Muammoli ish joylarini HEMISdan qayta sinxronlash}
                            {--all-users : Muammoliligidan qat’i nazar barcha foydalanuvchilarni HEMISdan sinxronlash}
                            {--hemis-id=* : Faqat ko‘rsatilgan HEMIS IDlarni sinxronlash}
                            {--limit=0 : HEMISdan sinxronlanadigan foydalanuvchilar sonini cheklash}
                            {--delay=100 : HEMIS so‘rovlari orasidagi kutish vaqti (millisekund)}
                            {--recalculate-only : Faqat faol hisobot ballarini qayta hisoblash}';

    protected $description = 'Reyting kategoriyasini asosiy, u bo‘lmasa qo‘shimcha ish joyi bo‘yicha aniqlaydi';

    public function handle(
        ResolveUserEvaluationCategory $resolveUserEvaluationCategory,
        RecalculateReportPoints $recalculateReportPoints,
        SyncHemisWorkplaces $syncHemisWorkplaces,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $syncHemis = (bool) $this->option('sync-hemis');

        if ($error = $this->optionValidationError($syncHemis)) {
            $this->error($error);

            return self::INVALID;
        }

        if ((bool) $this->option('recalculate-only')) {
            return $this->recalculateActiveReports($recalculateReportPoints)
                ? self::SUCCESS
                : self::FAILURE;
        }

        $statistics = [
            'total' => 0,
            'unchanged' => 0,
            'primary' => 0,
            'fallback' => 0,
            'missing' => 0,
            'duplicate' => 0,
            'sync_planned' => 0,
            'synced' => 0,
            'sync_failed' => 0,
        ];
        $changedUserIds = [];

        if ($syncHemis) {
            $candidateUserIds = $this->candidateUserIds();
            $statistics['sync_planned'] = count($candidateUserIds);
            $this->line("HEMISdan qayta sinxronlanadigan foydalanuvchilar: {$statistics['sync_planned']}");

            if (! $dryRun) {
                $this->syncProblematicUsers(
                    $candidateUserIds,
                    $syncHemisWorkplaces,
                    $statistics,
                    $changedUserIds,
                );
            }
        }

        $this->auditAndUpdateCategories(
            $resolveUserEvaluationCategory,
            $dryRun,
            $statistics,
            $changedUserIds,
        );

        $statistics['changed'] = count($changedUserIds);
        $recalculationSucceeded = true;

        if (! $dryRun && $statistics['changed'] > 0) {
            $recalculationSucceeded = $this->recalculateActiveReports($recalculateReportPoints);
        }

        $this->renderStatistics($statistics, $dryRun, $syncHemis);

        if (! $recalculationSucceeded) {
            $this->error(
                'Ballarni qayta hisoblash tugamadi. Tuzatishdan keyin '
                .'`php artisan kpi:ratings:backfill-primary-workplaces --recalculate-only` ni ishga tushiring.',
            );

            return self::FAILURE;
        }

        $unresolved = $statistics['missing'];

        if ($dryRun) {
            $this->info('Dry-run yakunlandi: HEMISga so‘rov yuborilmadi va bazaga o‘zgarish yozilmadi.');
        } elseif ($unresolved > 0 || $statistics['sync_failed'] > 0) {
            $this->warn(
                "{$unresolved} ta foydalanuvchining reyting ish joyi hal qilinmadi: "
                ."{$statistics['missing']} tasida ish joyi umuman yo‘q. "
                ."HEMIS sinxronlash xatolari: {$statistics['sync_failed']}.",
            );
        } elseif ($statistics['duplicate'] > 0) {
            $this->warn(
                "{$statistics['duplicate']} ta foydalanuvchida bir nechta asosiy ish joyi bor. "
                .'Reyting uchun deterministik ravishda bittasi tanlandi.',
            );
        } elseif ($statistics['changed'] > 0) {
            $this->info('Reyting kategoriyalari yangilandi va faol hisobot ballari qayta hisoblandi.');
        } elseif ($statistics['fallback'] > 0) {
            $this->info(
                "{$statistics['fallback']} ta foydalanuvchi asosiy ish joyi topilmagani uchun "
                .'qo‘shimcha ish joyi bo‘yicha reytingda qoldirildi.',
            );
        } else {
            $this->info('Barcha foydalanuvchilarning asosiy ish joyi va reyting kategoriyasi to‘g‘ri.');
        }

        return $unresolved > 0 || $statistics['sync_failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function optionValidationError(bool $syncHemis): ?string
    {
        if (! $syncHemis && (
            (bool) $this->option('all-users')
            || $this->option('hemis-id') !== []
            || (int) $this->option('limit') > 0
        )) {
            return '--all-users, --hemis-id va --limit faqat --sync-hemis bilan ishlatiladi.';
        }

        if ((int) $this->option('limit') < 0) {
            return '--limit manfiy bo‘lishi mumkin emas.';
        }

        foreach ($this->option('hemis-id') as $hemisId) {
            if (! is_numeric($hemisId)) {
                return "HEMIS ID [{$hemisId}] raqam bo‘lishi kerak.";
            }
        }

        return null;
    }

    /** @return array<int, int> */
    private function candidateUserIds(): array
    {
        $hasExplicitUsers = (bool) $this->option('all-users')
            || $this->option('hemis-id') !== [];
        $query = User::query()
            ->select('id')
            ->when(
                ! $hasExplicitUsers,
                fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                    $query->whereDoesntHave('workplaces');
                }),
            )
            ->when(
                $this->option('hemis-id') !== [],
                fn (Builder $query): Builder => $query->whereIn('hemis_id', $this->option('hemis-id')),
            )
            ->orderBy('id');
        $limit = min(1000, max(0, (int) $this->option('limit')));

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $candidateUserIds
     * @param  array<string, int>  $statistics
     * @param  array<int, bool>  $changedUserIds
     */
    private function syncProblematicUsers(
        array $candidateUserIds,
        SyncHemisWorkplaces $syncHemisWorkplaces,
        array &$statistics,
        array &$changedUserIds,
    ): void {
        $delayMilliseconds = min(5000, max(0, (int) $this->option('delay')));

        User::query()
            ->select(['id', 'hemis_id', 'degree'])
            ->whereKey($candidateUserIds)
            ->orderBy('id')
            ->chunkById(50, function (Collection $users) use (
                $syncHemisWorkplaces,
                $delayMilliseconds,
                &$statistics,
                &$changedUserIds,
            ): void {
                foreach ($users as $user) {
                    try {
                        $result = $syncHemisWorkplaces->handle($user);
                        $statistics['synced']++;

                        if ($result->degreeChanged) {
                            $changedUserIds[$user->getKey()] = true;
                        }
                    } catch (Throwable $exception) {
                        $statistics['sync_failed']++;
                        Log::error('HEMIS workplace backfill failed.', [
                            'user_id' => $user->getKey(),
                            'hemis_id' => $user->hemis_id,
                            'exception' => $exception,
                        ]);
                        $this->warn("HEMIS ID {$user->hemis_id}: sinxronlashda xatolik yuz berdi.");
                    }

                    if ($delayMilliseconds > 0) {
                        usleep($delayMilliseconds * 1000);
                    }
                }
            });
    }

    /**
     * @param  array<string, int>  $statistics
     * @param  array<int, bool>  $changedUserIds
     */
    private function auditAndUpdateCategories(
        ResolveUserEvaluationCategory $resolveUserEvaluationCategory,
        bool $dryRun,
        array &$statistics,
        array &$changedUserIds,
    ): void {
        User::query()
            ->withCount(['workplaces', 'primaryWorkplaces'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (
                $resolveUserEvaluationCategory,
                $dryRun,
                &$statistics,
                &$changedUserIds,
            ): void {
                foreach ($users as $user) {
                    $statistics['total']++;

                    if ($user->workplaces_count === 0) {
                        $statistics['missing']++;

                        continue;
                    }

                    if ($user->primary_workplaces_count > 1) {
                        $statistics['duplicate']++;
                    } elseif ($user->primary_workplaces_count === 0) {
                        $statistics['fallback']++;
                    } else {
                        $statistics['primary']++;
                    }

                    $degree = $resolveUserEvaluationCategory->handle($user);

                    if ($user->degree === $degree) {
                        if (! isset($changedUserIds[$user->getKey()])) {
                            $statistics['unchanged']++;
                        }

                        continue;
                    }

                    $changedUserIds[$user->getKey()] = true;

                    if (! $dryRun) {
                        $user->update(['degree' => $degree]);
                    }
                }
            });
    }

    private function recalculateActiveReports(RecalculateReportPoints $recalculateReportPoints): bool
    {
        $failedReports = 0;

        Report::query()
            ->where('status', '1')
            ->each(function (Report $report) use ($recalculateReportPoints, &$failedReports): void {
                try {
                    $recalculateReportPoints->handle($report);
                } catch (Throwable $exception) {
                    $failedReports++;
                    Log::error('Rating report recalculation failed after primary workplace sync.', [
                        'report_id' => $report->getKey(),
                        'exception' => $exception,
                    ]);
                    $this->error("Hisobot {$report->getKey()}: ballarni qayta hisoblashda xatolik.");
                }
            });

        return $failedReports === 0;
    }

    /** @param  array<string, int>  $statistics */
    private function renderStatistics(array $statistics, bool $dryRun, bool $syncHemis): void
    {
        $rows = [
            ['Jami foydalanuvchilar', $statistics['total']],
            [$dryRun ? 'O‘zgarishi kerak' : 'Tuzatildi', $statistics['changed']],
            ['To‘g‘ri bo‘lgan', $statistics['unchanged']],
            ['Asosiy ish joyidan baholanadi', $statistics['primary']],
            ['Qo‘shimcha ish joyidan baholanadi', $statistics['fallback']],
            ['Ish joyi umuman yo‘q', $statistics['missing']],
            ['Bir nechta asosiy ish joyi', $statistics['duplicate']],
        ];

        if ($syncHemis) {
            $rows[] = [$dryRun ? 'HEMIS sinxronlash rejalashtirildi' : 'HEMISdan qayta sinxronlandi',
                $dryRun ? $statistics['sync_planned'] : $statistics['synced']];
            $rows[] = ['HEMIS sinxronlash xatosi', $statistics['sync_failed']];
        }

        $this->table(['Holat', 'Soni'], $rows);
    }
}
