<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('jobs')->where('attempts', '>', 255)->exists()) {
            throw new RuntimeException('jobs.attempts ustunini toraytirish uchun barcha qiymatlar 255 dan oshmasligi kerak.');
        }

        Schema::table('jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->change();
        });
    }
};
