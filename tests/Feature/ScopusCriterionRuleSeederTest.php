<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Report;
use App\Support\ScopusCriterionRule;
use Database\Seeders\ScopusCriterionRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScopusCriterionRuleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_updates_scopus_rule_idempotently_without_using_a_fixed_criterion_id(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Ilmiy faoliyat'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => ScopusCriterionRule::NAME_UZ],
            'desc' => ['uz' => 'Eski qoida'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => 'author_count bo‘yicha eski qoida',
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);

        $this->seed(ScopusCriterionRuleSeeder::class);
        $this->seed(ScopusCriterionRuleSeeder::class);

        $criterion->refresh();

        $this->assertSame(ScopusCriterionRule::PROMPT, $criterion->ai_prompt);
        $this->assertSame(5.0, $criterion->ai_submission_max_point);
        $this->assertFalse($criterion->divide_ai_point_by_authors);
        $this->assertStringContainsString('bo‘linmaydi', $criterion->desc['uz']);
    }
}
