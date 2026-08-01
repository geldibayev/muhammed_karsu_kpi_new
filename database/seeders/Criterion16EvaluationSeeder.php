<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use Illuminate\Database\Seeder;

class Criterion16EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $criterion = Criterion::query()
            ->where('code', '2.1.4')
            ->whereHas('report', fn ($query) => $query->where('status', '1'))
            ->first();

        if ($criterion === null) {
            return;
        }

        $criterion->update(['upload' => '1', 'status' => '1']);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            CriterionEvaluation::query()->updateOrCreate(
                [
                    'criterion_id' => $criterion->getKey(),
                    'evaluation' => $evaluation,
                ],
                [
                    'has' => '1',
                    'score' => 4,
                ],
            );
        }
    }
}
