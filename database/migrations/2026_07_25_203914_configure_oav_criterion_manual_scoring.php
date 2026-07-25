<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CRITERION_CODE = '4/36';

    private const CRITERION_NAME = 'OAV yoki ijtimoiy tarmoqlarda universitet va mamlakatda amalga oshirilayotgan islohotlar yuzasidan chiqishlar qilganlig';

    private const FORMULA_NAME = 'Maksimal ballga asoslangan';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $criterionId = $this->criterionId();

        if ($criterionId === null) {
            return;
        }

        $formulaId = DB::table('formulas')
            ->get(['id', 'name'])
            ->first(
                fn (object $formula): bool => data_get(
                    json_decode((string) $formula->name, true),
                    'uz',
                ) === self::FORMULA_NAME,
            )?->id;

        if (! is_numeric($formulaId)) {
            throw new RuntimeException('Maksimal ballga asoslangan formula topilmadi.');
        }

        DB::transaction(function () use ($criterionId, $formulaId): void {
            DB::table('criteria')
                ->where('id', $criterionId)
                ->update([
                    'checking' => 'manual',
                    'file_limit' => 4,
                    'formula_id' => (int) $formulaId,
                    'updated_at' => now(),
                ]);

            DB::table('criterion_evaluations')
                ->where('criterion_id', $criterionId)
                ->where('has', '1')
                ->update([
                    'score' => 3,
                    'updated_at' => now(),
                ]);

            DB::table('criterion_manual_score_options')
                ->where('criterion_id', $criterionId)
                ->where('code', '!=', 'approved_resource')
                ->update([
                    'active' => false,
                    'updated_at' => now(),
                ]);

            DB::table('criterion_manual_score_options')->updateOrInsert(
                [
                    'criterion_id' => $criterionId,
                    'code' => 'approved_resource',
                ],
                [
                    'label' => json_encode(
                        ['uz' => 'Tasdiqlangan resurs'],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                    'point' => 0.75,
                    'sort_order' => 1,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }, 3);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $criterionId = $this->criterionId();

        if ($criterionId === null) {
            return;
        }

        DB::transaction(function () use ($criterionId): void {
            DB::table('criterion_manual_score_options')
                ->where('criterion_id', $criterionId)
                ->where('code', 'approved_resource')
                ->delete();

            DB::table('criteria')
                ->where('id', $criterionId)
                ->update([
                    'checking' => 'ai',
                    'file_limit' => 0,
                    'formula_id' => 1,
                    'updated_at' => now(),
                ]);

            DB::table('criterion_evaluations')
                ->where('criterion_id', $criterionId)
                ->where('evaluation', 'foreign_lang')
                ->update([
                    'score' => 2,
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    private function criterionId(): ?int
    {
        $criterionId = DB::table('criterion_reviewer_assignments')
            ->where('criterion_code', self::CRITERION_CODE)
            ->value('criterion_id');

        if (is_numeric($criterionId)) {
            return (int) $criterionId;
        }

        $criterion = DB::table('criteria')
            ->whereNotNull('parent_id')
            ->get(['id', 'name'])
            ->first(
                fn (object $criterion): bool => data_get(
                    json_decode((string) $criterion->name, true),
                    'uz',
                ) === self::CRITERION_NAME,
            );

        return isset($criterion->id) ? (int) $criterion->id : null;
    }
};
