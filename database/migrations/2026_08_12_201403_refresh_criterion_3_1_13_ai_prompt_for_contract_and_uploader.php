<?php

use App\Support\IndustryFundingCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', IndustryFundingCriterionRule::CODE)
            ->update([
                'ai_prompt' => IndustryFundingCriterionRule::PROMPT,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Forward-only domain correction: restoring the weaker AI validation is unsafe.
    }
};
