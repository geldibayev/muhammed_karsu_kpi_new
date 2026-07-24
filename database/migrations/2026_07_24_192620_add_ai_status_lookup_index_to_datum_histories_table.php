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
        Schema::table('datum_histories', function (Blueprint $table) {
            $table->index(
                ['message_type', 'created_at', 'id'],
                'datum_histories_ai_status_lookup_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datum_histories', function (Blueprint $table) {
            $table->dropIndex('datum_histories_ai_status_lookup_index');
        });
    }
};
