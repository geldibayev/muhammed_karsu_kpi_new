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
        Schema::create('disciplinary_sanctions', function (Blueprint $table) {
            $table->id();
            $table->string('hemis_id', 32)->unique();
            $table->foreignId('import_id')
                ->constrained('disciplinary_sanction_imports')
                ->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disciplinary_sanctions');
    }
};
