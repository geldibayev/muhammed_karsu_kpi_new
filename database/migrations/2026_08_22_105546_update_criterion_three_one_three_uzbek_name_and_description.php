<?php

use App\Support\ScopusCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', ScopusCriterionRule::CODE)
            ->update([
                'name->uz' => ScopusCriterionRule::NAME_UZ,
                'desc->uz' => ScopusCriterionRule::DESCRIPTION_UZ,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Production criterion wording is restored only through a forward migration.
    }
};
