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
        $this->configure(
            formulaCode: 'maximum',
            checking: 'site:disciplinary',
            scores: [
                'hold_degrees' => 2,
                'no_degrees' => 2,
                'foreign_lang' => 2,
                'physical' => 2,
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->configure(
            formulaCode: 'competition',
            checking: 'pointing',
            scores: [
                'hold_degrees' => 2,
                'no_degrees' => 3,
                'foreign_lang' => 1,
                'physical' => 2,
            ],
        );
    }

    /** @param  array<string, int>  $scores */
    private function configure(string $formulaCode, string $checking, array $scores): void
    {
        DB::transaction(function () use ($formulaCode, $checking, $scores): void {
            $formulaId = DB::table('formulas')->where('code', $formulaCode)->value('id');

            if ($formulaId === null) {
                return;
            }

            $criterionIds = DB::table('criteria')->where('code', '4.1.6')->pluck('id');
            DB::table('criteria')->whereIn('id', $criterionIds)->update([
                'formula_id' => $formulaId,
                'checking' => $checking,
                'upload' => '0',
                'updated_at' => now(),
            ]);

            foreach ($criterionIds as $criterionId) {
                foreach ($scores as $evaluation => $score) {
                    DB::table('criterion_evaluations')->updateOrInsert(
                        [
                            'criterion_id' => $criterionId,
                            'evaluation' => $evaluation,
                        ],
                        [
                            'has' => '1',
                            'score' => $score,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );
                }
            }
        });
    }
};
