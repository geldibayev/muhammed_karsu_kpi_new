<?php

use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE)
            ->update([
                'ai_prompt' => FixedPerResourceHumanReviewCriterionRule::fourOneOnePrompt(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only: restoring a prompt that accepted ma'lumotnoma evidence is unsafe.
    }
};
