<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('criterion_reviewer_assignments')
            ->where('criterion_code', '4.1.1')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
