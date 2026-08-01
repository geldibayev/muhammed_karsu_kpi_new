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
        Schema::table('criterion_years', function (Blueprint $table) {
            $table->unique(['criterion_id', 'year_id'], 'criterion_years_criterion_year_uq');
        });

        Schema::table('datum_authors', function (Blueprint $table) {
            $table->unique(['datum_id', 'user_id'], 'datum_authors_datum_user_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datum_authors', function (Blueprint $table) {
            $table->dropUnique('datum_authors_datum_user_uq');
        });

        Schema::table('criterion_years', function (Blueprint $table) {
            $table->dropUnique('criterion_years_criterion_year_uq');
        });
    }
};
