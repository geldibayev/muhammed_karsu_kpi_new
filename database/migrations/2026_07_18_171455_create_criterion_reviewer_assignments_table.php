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
        Schema::create('criterion_reviewer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->unique()->constrained('criteria')->cascadeOnDelete();
            $table->unsignedBigInteger('hemis_id')->index();
            $table->string('criterion_code')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('criterion_reviewer_assignments');
    }
};
