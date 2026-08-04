<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Report;
use App\Support\ProfessionalDevelopmentCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfessionalDevelopmentAiConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configuration_migration_repairs_prompt_and_category_maximums(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => ProfessionalDevelopmentCriterionRule::CODE,
            'name' => ['uz' => 'Malaka oshirish'],
            'desc' => ['uz' => 'Eski tavsif'],
            'report_id' => $report->getKey(),
            'checking' => 'manual',
            'ai_prompt' => 'Reytingni taxmin qiling va 2 ball bering.',
            'file_limit' => 0,
            'res_type' => 'all',
            'divide_ai_point_by_authors' => true,
            'upload' => '1',
            'status' => '1',
        ]);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluationCode) {
            Evaluation::query()->create([
                'code' => $evaluationCode,
                'name' => ['uz' => $evaluationCode],
                'status' => '1',
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluationCode,
                'has' => '1',
                'score' => 1,
            ]);
        }

        $migration = require database_path('migrations/2026_08_04_160052_fix_criterion_2_1_5_ai_scoring.php');
        $migration->up();

        $criterion->refresh()->load('criterionEvaluations');

        $this->assertSame('ai', $criterion->checking);
        $this->assertSame(ProfessionalDevelopmentCriterionRule::PROMPT, $criterion->ai_prompt);
        $this->assertSame(ProfessionalDevelopmentCriterionRule::DESCRIPTION_UZ, $criterion->desc['uz']);
        $this->assertSame(1, $criterion->file_limit);
        $this->assertSame('file', $criterion->res_type);
        $this->assertFalse($criterion->divide_ai_point_by_authors);
        $this->assertSame(2, $criterion->criterionEvaluations->firstWhere('evaluation', 'hold_degrees')?->score);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'no_degrees')?->score);
        $this->assertSame(2, $criterion->criterionEvaluations->firstWhere('evaluation', 'foreign_lang')?->score);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'physical')?->score);
    }
}
