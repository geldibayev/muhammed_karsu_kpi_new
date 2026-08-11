<?php

use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('criteria')
            ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE)
            ->exists()) {
            return;
        }

        DB::table('criteria')
            ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE)
            ->update([
                'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
                'updated_at' => now(),
            ]);

        $exitCode = Artisan::call('kpi:criteria:backfill-fixed-resource-points', [
            '--criterion' => FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('4.1.1 resurs ballarini qayta hisoblash bajarilmadi.');
        }
    }

    public function down(): void
    {
        // Forward-only domain correction: restoring incorrect accepted points is unsafe.
    }
};
