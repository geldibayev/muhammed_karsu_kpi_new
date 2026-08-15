<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->whereIn('report_id', DB::table('reports')->where('status', '1')->select('id'))
            ->whereIn('code', ['3.1.3', '3.1.11'])
            ->update([
                'file_limit' => 10,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('criteria')
            ->whereIn('report_id', DB::table('reports')->where('status', '1')->select('id'))
            ->whereIn('code', ['3.1.3', '3.1.11'])
            ->update([
                'file_limit' => 4,
                'updated_at' => now(),
            ]);
    }
};
