<?php

use App\Support\PatentCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('criteria')
                ->where('code', PatentCriterionRule::CODE)
                ->orderBy('id')
                ->get(['id', 'desc'])
                ->each(function (object $criterion): void {
                    $description = is_string($criterion->desc)
                        ? json_decode($criterion->desc, true)
                        : [];
                    $description = is_array($description) ? $description : [];
                    $description['uz'] = PatentCriterionRule::DESCRIPTION_UZ;

                    DB::table('criteria')
                        ->where('id', $criterion->id)
                        ->update([
                            'desc' => json_encode($description, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                });
        }, 3);
    }

    public function down(): void
    {
        // Production criterion wording is restored only through a forward migration.
    }
};
