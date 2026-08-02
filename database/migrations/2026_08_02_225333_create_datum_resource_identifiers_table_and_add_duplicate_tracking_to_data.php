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
            $table->foreignId('duplicate_of_id')
                ->nullable()
                ->after('reason')
                ->constrained('data')
                ->nullOnDelete();
        });

        Schema::create('datum_resource_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datum_id')->constrained('data')->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->char('value_hash', 64);
            $table->char('active_value_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['datum_id', 'type', 'value_hash'],
                'datum_identifiers_datum_type_hash_unique',
            );
            $table->unique(
                ['report_id', 'user_id', 'type', 'active_value_hash'],
                'datum_identifiers_active_unique',
            );
            $table->index(['type', 'value_hash'], 'datum_identifiers_type_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datum_resource_identifiers');

        Schema::table('data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
        });
    }
};
