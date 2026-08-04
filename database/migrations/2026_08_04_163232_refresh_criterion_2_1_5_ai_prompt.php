<?php

use App\Support\ProfessionalDevelopmentCriterionRule;
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
            ->where('code', ProfessionalDevelopmentCriterionRule::CODE)
            ->update([
                'ai_prompt' => ProfessionalDevelopmentCriterionRule::PROMPT,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
