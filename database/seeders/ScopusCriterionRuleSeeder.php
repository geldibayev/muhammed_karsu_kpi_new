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
        $criteria = Criterion::query()
            ->whereNotNull('parent_id')
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->get(['id', 'name', 'desc']);
        $scopusCriteria = $criteria->filter(
            fn (Criterion $criterion): bool => data_get($criterion->name, 'uz') === ScopusCriterionRule::NAME_UZ,
        );

        if ($scopusCriteria->count() !== 1) {
            throw new RuntimeException('3/22 SCOPUS kriteriyasi yagona yozuv sifatida topilmadi.');
        }

        $criterion = $scopusCriteria->firstOrFail();
        $description = is_array($criterion->desc) ? $criterion->desc : [];

        $criterion->update([
            'desc' => [
                ...$description,
                'uz' => 'Scopus va Web of Science bazasi orqali baholanadi. Q1, Q2 — 100 %, Q3, Q4 — 80 %, konferensiya maqolalari — 50 %. Ball mualliflar soniga bo‘linmaydi.',
                'kaa' => 'Scopus hám Web of Science bazası arqalı bahalanadı. Q1, Q2 — 100 %, Q3, Q4 — 80 %, konferenciya maqalaları — 50 %. Ball avtorlar sanına bólinbeydi.',
                'ru' => 'Оценивается по базам Scopus и Web of Science. Q1, Q2 — 100 %, Q3, Q4 — 80 %, статьи конференций — 50 %. Баллы не делятся на количество авторов.',
                'en' => 'Evaluated using Scopus and Web of Science. Q1 and Q2 — 100%, Q3 and Q4 — 80%, conference papers — 50%. Points are not divided by the number of authors.',
            ],
            'ai_prompt' => ScopusCriterionRule::PROMPT,
            'ai_submission_max_point' => ScopusCriterionRule::MAXIMUM_POINT,
            'divide_ai_point_by_authors' => false,
        ]);
    }
}
