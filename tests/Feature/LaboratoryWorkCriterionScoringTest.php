<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Support\KpiCriterionSpecification;
use App\Support\LaboratoryWorkCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LaboratoryWorkCriterionScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_only_approves_and_server_calculates_point_from_author_count(): void
    {
        $result = LaboratoryWorkCriterionRule::apply(new AiEvaluationResult(
            status: 'accepted',
            point: 0,
            reason: 'Laboratoriya ishi va to‘rt muallif hujjatda tasdiqlandi.',
            authorCount: 4,
        ));

        $this->assertSame('accepted', $result->status);
        $this->assertSame(4, $result->authorCount);
        $this->assertSame(0.125, $result->point);
        $this->assertStringContainsString('0.5 / 4', $result->reason);

        $missingAuthors = LaboratoryWorkCriterionRule::apply(new AiEvaluationResult(
            status: 'accepted',
            point: 0,
            reason: 'Resurs mos.',
        ));

        $this->assertSame('checking', $missingAuthors->status);
        $this->assertSame(0.0, $missingAuthors->point);
    }

    public function test_specification_uses_four_resources_and_maximum_formula_without_generic_ai_division(): void
    {
        $rule = KpiCriterionSpecification::currentCriteria()[LaboratoryWorkCriterionRule::CURRENT_CODE];

        $this->assertSame(KpiCriterionSpecification::Maximum, $rule['formula']);
        $this->assertSame(4, $rule['file_limit']);
        $this->assertSame(0.5, $rule['ai_submission_max_point']);
        $this->assertFalse($rule['divide_ai_point_by_authors']);
        $this->assertStringContainsString('author_count', $rule['ai_prompt']);
    }

    public function test_human_fallback_requires_author_count_and_uses_same_server_formula(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $criterion = Criterion::query()->create([
            'code' => LaboratoryWorkCriterionRule::CURRENT_CODE,
            'name' => ['uz' => 'Laboratoriya ishlari'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => LaboratoryWorkCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 2,
        ]);
        $datum = Datum::query()->create([
            'name' => 'Virtual laboratoriya',
            'material' => ['type' => 'file', 'path' => 'laboratory.pdf'],
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Resursdagi jami mualliflar soni')
            ->assertSee('name="author_count"', false)
            ->assertDontSee('name="point"', false);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertSessionHasErrors('author_count');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), ['author_count' => 4])
            ->assertRedirect(route('ai-human-reviews.index'));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(4, $datum->author_count);
        $this->assertSame(0.125, $datum->point);
    }
}
