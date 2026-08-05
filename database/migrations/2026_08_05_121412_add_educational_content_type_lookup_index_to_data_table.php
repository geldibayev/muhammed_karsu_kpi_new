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
        Schema::table('data', function (Blueprint $table) {
            $table->index(
                ['user_id', 'criterion_id', 'status', 'manual_score_option_id'],
                'data_user_criterion_status_manual_option_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropIndex('data_user_criterion_status_manual_option_index');
        });
    }
};
