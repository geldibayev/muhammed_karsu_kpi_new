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
        Schema::table('criterion_points', function (Blueprint $table) {
            $table->unique(
                ['report_id', 'user_id', 'criterion_id'],
                'criterion_points_report_user_criterion_unique',
            );
        });

        Schema::table('points', function (Blueprint $table) {
            $table->unique(
                ['report_id', 'user_id', 'criterion_id'],
                'points_report_user_criterion_unique',
            );
        });

        Schema::table('criterion_evaluations', function (Blueprint $table) {
            $table->unique(
                ['criterion_id', 'evaluation'],
                'criterion_evaluations_criterion_evaluation_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('criterion_points', function (Blueprint $table) {
            $table->dropUnique('criterion_points_report_user_criterion_unique');
        });

        Schema::table('points', function (Blueprint $table) {
            $table->dropUnique('points_report_user_criterion_unique');
        });

        Schema::table('criterion_evaluations', function (Blueprint $table) {
            $table->dropUnique('criterion_evaluations_criterion_evaluation_unique');
        });
    }
};
