<?php

use App\Support\TranslatedEducationalLiteratureCriterionRule;
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
            ->where('code', TranslatedEducationalLiteratureCriterionRule::CODE)
            ->update([
                'ai_prompt' => TranslatedEducationalLiteratureCriterionRule::PROMPT,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
