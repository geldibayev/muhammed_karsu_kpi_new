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
        Schema::table('data', function (Blueprint $table): void {
            $table->unsignedSmallInteger('impact_factor')->nullable()->after('page_count');
            $table->string('publication_tier', 20)->nullable()->after('impact_factor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table): void {
            $table->dropColumn(['impact_factor', 'publication_tier']);
        });
    }
};
