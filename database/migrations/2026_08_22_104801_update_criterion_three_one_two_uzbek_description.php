<?php

use App\Models\Criterion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('criteria')
            ->where('code', Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE)
            ->update([
                'desc->uz' => 'Har bir tasdiqlangan resurs uchun ilmiy darajaga ega foydalanuvchiga 0,5 ball, ilmiy darajaga ega bo‘lmagan foydalanuvchiga 0,75 ball beriladi. Ball maqoladagi jami mualliflar soniga teng taqsimlanadi.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Production criterion wording is restored only through a forward migration.
    }
};
