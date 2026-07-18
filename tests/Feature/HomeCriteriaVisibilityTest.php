<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Database\Seeders\Criterion16EvaluationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeCriteriaVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_criterion_16_is_visible_and_uploadable_for_every_degree(): void
    {
        Storage::fake('local');
        $user = User::factory()->withRole('user')->create(['degree' => 'hold_degrees']);
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluationCode) {
            Evaluation::query()->create([
                'code' => $evaluationCode,
                'name' => ['uz' => $evaluationCode],
            ]);
        }
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
            'res_type' => 'file',
            'template' => '0',
        ]);
        $this->seed(Criterion16EvaluationSeeder::class);
        $this->seed(Criterion16EvaluationSeeder::class);
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('2/16')
            ->assertSee('Xalqaro loyihalarda ishtiroki')
            ->assertSee('4.00')
            ->assertSee(route('upload.show', $applicableCriterion))
            ->assertSee(route('upload.show', $criterionWithoutEvaluation));

        $this->actingAs($user)
            ->get(route('upload.show', $criterionWithoutEvaluation))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('upload.store', $criterionWithoutEvaluation), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->create('xalqaro-loyiha.pdf', 100, 'application/pdf'),
                'year' => $year->id,
            ])
            ->assertRedirect(route('upload.show', $criterionWithoutEvaluation));

        $this->assertDatabaseHas('data', [
            'criterion_id' => 16,
            'user_id' => $user->id,
            'status' => 'received',
        ]);
        $this->assertDatabaseCount('criterion_evaluations', 5);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluationCode) {
            $this->assertDatabaseHas('criterion_evaluations', [
                'criterion_id' => 16,
                'evaluation' => $evaluationCode,
                'has' => '1',
                'score' => 4,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(Report $report, array $attributes): Criterion
    {
        return Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'desc' => ['uz' => 'Test mezoni tavsifi'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ], $attributes));
    }
}
