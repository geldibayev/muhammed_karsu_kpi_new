<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Actions\ResolveUserEvaluationCategory;
use App\Models\Report;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillPrimaryWorkplaceRatings extends Command
{
    protected $signature = 'kpi:ratings:backfill-primary-workplaces
                            {--dry-run : O‘zgarishlarni bazaga yozmasdan natijani ko‘rsatish}';

    protected $description = 'Reyting kategoriyasini HEMISdagi asosiy ish joyi bo‘yicha qayta aniqlaydi';

    public function handle(
        ResolveUserEvaluationCategory $resolveUserEvaluationCategory,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $statistics = [
            'total' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'missing' => 0,
            'duplicate' => 0,
        ];

        User::query()
            ->withCount('primaryWorkplaces')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (
                $resolveUserEvaluationCategory,
                $dryRun,
                &$statistics,
            ): void {
                foreach ($users as $user) {
                    $statistics['total']++;

                    if ($user->primary_workplaces_count === 0) {
                        $statistics['missing']++;

                        continue;
                    }

                    if ($user->primary_workplaces_count > 1) {
                        $statistics['duplicate']++;
                        $this->warn(
                            "HEMIS ID {$user->hemis_id}: bir nechta asosiy ish joyi topildi, o‘zgartirilmadi.",
                        );

                        continue;
                    }

                    $degree = $resolveUserEvaluationCategory->handle($user);

                    if ($user->degree === $degree) {
                        $statistics['unchanged']++;

                        continue;
                    }

                    $statistics['changed']++;

                    if (! $dryRun) {
                        $user->update(['degree' => $degree]);
                    }
                }
            });

        if (! $dryRun && $statistics['changed'] > 0) {
            Report::query()
                ->where('status', '1')
                ->each(fn (Report $report) => $recalculateReportPoints->handle($report));
        }

        $this->table(['Holat', 'Soni'], [
            ['Jami foydalanuvchilar', $statistics['total']],
            [$dryRun ? 'O‘zgarishi kerak' : 'Tuzatildi', $statistics['changed']],
            ['To‘g‘ri bo‘lgan', $statistics['unchanged']],
            ['Asosiy ish joyi yo‘q', $statistics['missing']],
            ['Bir nechta asosiy ish joyi', $statistics['duplicate']],
        ]);

        if ($dryRun) {
            $this->info('Dry-run yakunlandi: bazaga hech qanday o‘zgarish yozilmadi.');
        } elseif ($statistics['changed'] > 0) {
            $this->info('Reyting kategoriyalari yangilandi va faol hisobot ballari qayta hisoblandi.');
        } else {
            $this->info('O‘zgartirish talab qilinmadi.');
        }

        return $statistics['duplicate'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
