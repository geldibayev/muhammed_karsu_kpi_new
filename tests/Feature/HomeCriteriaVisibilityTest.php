<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomeCriteriaVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_all_criteria_are_visible_when_user_degree_has_no_evaluation(): void
    {
        $user = User::factory()->create(['degree' => 'hold_degrees']);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $firstParent = $this->createCriterion($report, [
            'id' => 1,
            'name' => ['uz' => 'Birinchi bo\'lim'],
        ]);
        $applicableCriterion = $this->createCriterion($report, [
            'id' => 2,
            'parent_id' => $firstParent->id,
            'name' => ['uz' => 'Darajaga mos mezon'],
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $applicableCriterion->id,
            'evaluation' => $user->degree,
            'has' => '1',
            'score' => 5,
        ]);
        $secondParent = $this->createCriterion($report, [
            'id' => 12,
            'name' => ['uz' => 'Ikkinchi bo\'lim'],
        ]);
        $criterionWithoutEvaluation = $this->createCriterion($report, [
            'id' => 16,
            'parent_id' => $secondParent->id,
            'name' => ['uz' => 'Xalqaro loyihalarda ishtiroki'],
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('2/16')
            ->assertSee('Xalqaro loyihalarda ishtiroki')
            ->assertSee(route('upload.show', $applicableCriterion))
            ->assertSee(route('upload.show', $criterionWithoutEvaluation));
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(Report $report, array $attributes): Criterion
    {
        return Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ], $attributes));
    }
}
