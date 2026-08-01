<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('criteria', function (Blueprint $table): void {
            $table->string('ai_model', 255)
                ->default('gemini-3.5-flash-lite')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('criteria', function (Blueprint $table): void {
            $table->string('ai_model', 255)
                ->default('gemini-2.5-flash')
                ->change();
        });
    }
};
