<?php

use App\Support\HIndexCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', HIndexCriterionRule::CODE)
            ->update([
                'desc->uz' => HIndexCriterionRule::DESCRIPTION_UZ,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Production criterion wording is restored only through a forward migration.
    }
};
