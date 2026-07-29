<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table): void {
            $table->unsignedBigInteger('reviewer_hemis_id')
                ->nullable()
                ->after('criterion_id');
            $table->index(
                ['reviewer_hemis_id', 'status', 'created_at'],
                'data_reviewer_status_created_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table): void {
            $table->dropIndex('data_reviewer_status_created_at_index');
            $table->dropColumn('reviewer_hemis_id');
        });
    }
};
