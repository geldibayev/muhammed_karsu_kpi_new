<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', '3.1.9')
            ->update([
                'name->uz' => 'Axborot-kommunikatsiya texnologiyalariga oid dasturlar va ma’lumotlar bazalari uchun olingan guvohnomalar',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Production criterion wording is restored only through a forward migration.
    }
};
