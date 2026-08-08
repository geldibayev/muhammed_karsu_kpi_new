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
        DB::table('users')
            ->whereIn('hemis_id', config('kpi.super_admin_hemis_ids', []))
            ->get(['id', 'rol'])
            ->each(function (object $user): void {
                $roles = json_decode((string) $user->rol, true);
                $roles = is_array($roles) ? $roles : [];

                DB::table('users')->where('id', $user->id)->update([
                    'rol' => json_encode(array_values(array_unique([
                        ...$roles,
                        'super_admin',
                        'teacher',
                    ])), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing administrator roles are deliberately preserved on rollback.
    }
};
