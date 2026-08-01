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
        Schema::table('criteria', function (Blueprint $table) {
            $table->decimal('ai_submission_max_point', 8, 2)->nullable()->after('ai_model');
            $table->boolean('divide_ai_point_by_authors')->nullable()->after('ai_submission_max_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('criteria', function (Blueprint $table) {
            $table->dropColumn(['ai_submission_max_point', 'divide_ai_point_by_authors']);
        });
    }
};
