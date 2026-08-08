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
            $table->string('system_key', 64)->nullable()->after('criterion_id');
            $table->unique(
                ['user_id', 'criterion_id', 'system_key'],
                'data_user_criterion_system_key_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropUnique('data_user_criterion_system_key_unique');
            $table->dropColumn('system_key');
        });
    }
};
