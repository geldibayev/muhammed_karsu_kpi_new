<?php

use App\Support\IndustryFundingCriterionRule;
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
            ->where('code', IndustryFundingCriterionRule::CODE)
            ->update([
                'ai_prompt' => IndustryFundingCriterionRule::PROMPT,
                'divide_ai_point_by_authors' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
