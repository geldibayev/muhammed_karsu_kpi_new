<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CRITERION_CODE = '2.1.4';

    public function up(): void
    {
        $reviewers = config('kpi.criterion_reviewers', []);

        $this->assign((int) ($reviewers[self::CRITERION_CODE] ?? 0));
    }

    public function down(): void
    {
        $this->assign((int) config('kpi.settings_manager_hemis_id'));
    }

    private function assign(int $hemisId): void
    {
        $criterionId = DB::table('criteria')
            ->join('reports', 'reports.id', '=', 'criteria.report_id')
            ->where('criteria.code', self::CRITERION_CODE)
            ->where('reports.status', '1')
            ->value('criteria.id');

        if (! is_numeric($criterionId)) {
            return;
        }

        if ($hemisId <= 0) {
            throw new RuntimeException('2.1.4 mezoni mas’ulining HEMIS ID sozlamasi noto‘g‘ri.');
        }

        DB::transaction(function () use ($criterionId, $hemisId): void {
            DB::table('criterion_reviewer_assignments')->updateOrInsert(
                ['criterion_id' => (int) $criterionId],
                fn (bool $exists): array => [
                    'criterion_code' => self::CRITERION_CODE,
                    'hemis_id' => $hemisId,
                    'updated_at' => now(),
                    ...($exists ? [] : ['created_at' => now()]),
                ],
            );
        }, 3);
    }
};
