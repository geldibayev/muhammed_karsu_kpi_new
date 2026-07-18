<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use Illuminate\Database\Seeder;

class Criterion16EvaluationSeeder extends Seeder
{
    private const CRITERION_ID = 16;

    private const MAXIMUM_SCORE = 4;

    private const EVALUATION_CODES = [
        'hold_degrees',
        'no_degrees',
        'foreign_lang',
        'physical',
    ];

    public function run(): void
    {
        $criterion = Criterion::query()->find(self::CRITERION_ID);

        if ($criterion === null) {
            return;
        }

        $criterion->update([
            'upload' => '1',
            'status' => '1',
        ]);

        $evaluations = Evaluation::query()
            ->whereIn('code', self::EVALUATION_CODES)
            ->pluck('code')
            ->map(fn (string $code): array => [
                'criterion_id' => self::CRITERION_ID,
                'evaluation' => $code,
                'has' => '1',
                'score' => self::MAXIMUM_SCORE,
            ])
            ->values()
            ->all();

        if ($evaluations === []) {
            return;
        }

        CriterionEvaluation::query()->upsert(
            $evaluations,
            ['criterion_id', 'evaluation'],
            ['has', 'score'],
        );
    }
}
