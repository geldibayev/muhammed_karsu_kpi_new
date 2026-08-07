<?php

use App\Models\Formula;
use App\Support\ForeignLanguageCertificateCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('criteria')
            ->where('code', ForeignLanguageCertificateCriterionRule::CODE)
            ->exists()) {
            return;
        }

        $maximumFormulaId = DB::table('formulas')
            ->where('code', Formula::Maximum)
            ->value('id');

        if ($maximumFormulaId === null) {
            throw new RuntimeException('Maksimal ball formulasi topilmadi.');
        }

        DB::transaction(function () use ($maximumFormulaId): void {
            DB::table('criteria')
                ->where('code', ForeignLanguageCertificateCriterionRule::CODE)
                ->orderBy('id')
                ->get(['id', 'desc'])
                ->each(function (object $criterion) use ($maximumFormulaId): void {
                    $description = json_decode((string) $criterion->desc, true);
                    $description = is_array($description) ? $description : [];
                    $description['uz'] = ForeignLanguageCertificateCriterionRule::DESCRIPTION_UZ;

                    DB::table('criteria')
                        ->where('id', $criterion->id)
                        ->update([
                            'formula_id' => $maximumFormulaId,
                            'file_limit' => 1,
                            'checking' => 'manual',
                            'upload' => '1',
                            'desc' => json_encode($description, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);

                    foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
                        DB::table('criterion_evaluations')->updateOrInsert(
                            [
                                'criterion_id' => $criterion->id,
                                'evaluation' => $evaluation,
                            ],
                            [
                                'has' => '1',
                                'score' => 10,
                                'updated_at' => now(),
                            ],
                        );
                    }

                    foreach ([
                        'a1' => ['A1 sertifikat', 0, 1],
                        'a2' => ['A2 sertifikat', 0, 2],
                        'b1' => ['B1 sertifikat', 3, 3],
                        'b2' => ['B2 sertifikat', 6, 4],
                        'c1' => ['C1 sertifikat', 8, 5],
                        'c2' => ['C2 sertifikat', 10, 6],
                    ] as $code => [$label, $point, $sortOrder]) {
                        DB::table('criterion_manual_score_options')->updateOrInsert(
                            ['criterion_id' => $criterion->id, 'code' => $code],
                            [
                                'label' => json_encode(['uz' => $label], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                                'point' => $point,
                                'sort_order' => $sortOrder,
                                'active' => true,
                                'updated_at' => now(),
                            ],
                        );
                    }
                });
        }, 3);
    }

    public function down(): void
    {
        // Production scoring decisions are restored only through a forward migration.
    }
};
