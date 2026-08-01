<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('criteria')
                ->where('ai_model', 'gemini-2.5-flash')
                ->update([
                    'ai_model' => 'gemini-3.5-flash-lite',
                    'updated_at' => now(),
                ]);

            DB::table('criteria')
                ->where('ai_model', 'gemini-2.5-pro')
                ->update([
                    'ai_model' => 'gemini-3.5-flash',
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('criteria')
                ->where('ai_model', 'gemini-3.5-flash-lite')
                ->update([
                    'ai_model' => 'gemini-2.5-flash',
                    'updated_at' => now(),
                ]);

            DB::table('criteria')
                ->where('ai_model', 'gemini-3.5-flash')
                ->update([
                    'ai_model' => 'gemini-2.5-pro',
                    'updated_at' => now(),
                ]);
        }, 3);
    }
};
