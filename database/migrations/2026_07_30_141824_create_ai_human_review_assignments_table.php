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
        Schema::create('ai_human_review_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->index();
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_human_review_assignments');
    }
};
