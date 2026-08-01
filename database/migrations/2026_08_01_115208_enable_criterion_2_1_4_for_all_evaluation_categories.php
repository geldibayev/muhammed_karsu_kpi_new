<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CRITERION_CODE = '2.1.4';

    private const EVALUATIONS = [
        'hold_degrees',
        'no_degrees',
        'foreign_lang',
        'physical',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->criterionIds()->each(function (int $criterionId): void {
                foreach (self::EVALUATIONS as $evaluation) {
                    $this->updateEvaluation($criterionId, $evaluation, '1', 4);
                }
            });
        }, 3);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->criterionIds()->each(function (int $criterionId): void {
                foreach (self::EVALUATIONS as $evaluation) {
                    $isForeignLanguageCategory = $evaluation === 'foreign_lang';
                    $this->updateEvaluation(
                        $criterionId,
                        $evaluation,
                        $isForeignLanguageCategory ? '1' : '0',
                        $isForeignLanguageCategory ? 4 : 0,
                    );
                }
            });
        }, 3);
    }

    /** @return Collection<int, int> */
    private function criterionIds(): Collection
    {
        return DB::table('criteria')
            ->join('reports', 'reports.id', '=', 'criteria.report_id')
            ->where('criteria.code', self::CRITERION_CODE)
            ->where('reports.status', '1')
            ->pluck('criteria.id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    private function updateEvaluation(int $criterionId, string $evaluation, string $has, int $score): void
    {
        DB::table('criterion_evaluations')->updateOrInsert(
            [
                'criterion_id' => $criterionId,
                'evaluation' => $evaluation,
            ],
            fn (bool $exists): array => [
                'has' => $has,
                'score' => $score,
                'updated_at' => now(),
                ...($exists ? [] : ['created_at' => now()]),
            ],
        );
    }
};
