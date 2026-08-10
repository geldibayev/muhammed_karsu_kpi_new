<?php

use App\Support\OakArticleCriterionRule;
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
            ->where('code', OakArticleCriterionRule::CODE)
            ->update(['ai_prompt' => OakArticleCriterionRule::PROMPT]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous prompt varied between environments and cannot be restored safely.
    }
};
