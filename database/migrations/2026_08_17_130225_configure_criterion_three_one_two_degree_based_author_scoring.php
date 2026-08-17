<?php

use App\Models\Criterion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('criteria')
                ->where('code', Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE)
                ->update([
                    'file_limit' => 4,
                    'divide_ai_point_by_authors' => true,
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    public function down(): void
    {
        // Production scoring rules are restored only through a forward migration.
    }
};
