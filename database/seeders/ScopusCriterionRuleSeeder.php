<?php

namespace Database\Seeders;

use App\Models\Criterion;
use App\Support\ScopusCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use RuntimeException;

class ScopusCriterionRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scopusCriteria = Criterion::query()
            ->where('code', ScopusCriterionRule::CODE)
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->get(['id', 'code', 'name', 'desc']);

        if ($scopusCriteria->count() !== 1) {
            throw new RuntimeException('3.1.3 SCOPUS mezoni yagona yozuv sifatida topilmadi.');
        }

        $criterion = $scopusCriteria->firstOrFail();
        $name = is_array($criterion->name) ? $criterion->name : [];
        $description = is_array($criterion->desc) ? $criterion->desc : [];

        $criterion->update([
            'name' => [
                ...$name,
                'uz' => ScopusCriterionRule::NAME_UZ,
            ],
            'desc' => [
                ...$description,
                'uz' => ScopusCriterionRule::DESCRIPTION_UZ,
                'kaa' => 'Scopus hám Web of Science bazalarında indekslengen basılımlar server tárepinen bahalanadı: Q1 — 20 ball, Q2 — 15 ball, Q3 — 10 ball, Q4 — 5 ball, Scopus yamasa Web of Science konferenciya materialı — 5 ball. Ball avtorlar sanına bólinbeydi.',
                'ru' => 'Индексируемые в Scopus и Web of Science публикации оцениваются сервером: Q1 — 20 баллов, Q2 — 15, Q3 — 10, Q4 — 5, материал конференции Scopus или Web of Science — 5 баллов. Баллы не делятся на количество авторов.',
                'en' => 'Scopus and Web of Science indexed publications are scored by the server: Q1 — 20 points, Q2 — 15, Q3 — 10, Q4 — 5, and a Scopus or Web of Science conference paper — 5 points. Points are not divided by the number of authors.',
            ],
            'ai_prompt' => ScopusCriterionRule::PROMPT,
            'ai_submission_max_point' => ScopusCriterionRule::MAXIMUM_POINT,
            'divide_ai_point_by_authors' => false,
        ]);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            $criterion->criterionEvaluations()->updateOrCreate(
                ['evaluation' => $evaluation],
                [
                    'has' => '1',
                    'score' => ScopusCriterionRule::MAXIMUM_POINT,
                ],
            );
        }
    }
}
