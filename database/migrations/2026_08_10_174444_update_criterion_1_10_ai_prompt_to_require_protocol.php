<?php

use App\Support\MasterClassCriterionRule;
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
            ->where('code', MasterClassCriterionRule::CODE)
            ->update([
                'ai_prompt' => MasterClassCriterionRule::PROMPT,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
