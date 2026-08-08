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
        Schema::create('disciplinary_sanction_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_file');
            $table->char('source_hash', 64)->index();
            $table->unsignedInteger('row_count');
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disciplinary_sanction_imports');
    }
};
