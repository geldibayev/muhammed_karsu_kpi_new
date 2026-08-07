<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Database\Seeders\Criterion16EvaluationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeCriteriaVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_and_submission_policy_are_scoped_to_the_active_report(): void
    {
        $user = User::factory()->withRole('user')->create(['degree' => 'hold_degrees']);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
        ]);
        $inactiveReport = Report::query()->create(['name' => ['uz' => 'Eski'], 'status' => '0']);
        $activeReport = Report::query()->create(['name' => ['uz' => 'Faol'], 'status' => '1']);
        $maximumFormula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ballgacha'],
            'status' => '1',
        ]);
        $inactiveParent = $this->createCriterion($inactiveReport, ['name' => ['uz' => 'Eski bo‘lim']]);
        $inactiveCriterion = $this->createCriterion($inactiveReport, [
            'name' => ['uz' => 'Eski hisobot mezoni'],
            'parent_id' => $inactiveParent->getKey(),
        ]);
        $activeParent = $this->createCriterion($activeReport, ['name' => ['uz' => 'Faol bo‘lim']]);
        $activeCriterion = $this->createCriterion($activeReport, [
            'code' => '1.2',
            'name' => ['uz' => 'Faol hisobot mezoni'],
            'formula_id' => $maximumFormula->getKey(),
            'parent_id' => $activeParent->getKey(),
        ]);

        foreach ([$inactiveCriterion, $activeCriterion] as $criterion) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => 'hold_degrees',
                'has' => '1',
                'score' => 2,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Faol hisobot mezoni')
            ->assertSee('Asosiy indikatorlar')
            ->assertSee('Asosiy indikator')
            ->assertSee('Minimal ball')
            ->assertSee('maksimal ball chegaralanmagan')
            ->assertSee('data-testid="primary-indicator-row"', false)
            ->assertSee('Baholash usuli')
            ->assertSee('Maksimal ballgacha')
            ->assertSee('data-testid="rating-method-button"', false)
            ->assertSee('data-target="#rating-method-'.$activeCriterion->getKey().'"', false)
            ->assertSee('Sizning toifangiz uchun maksimal ball')
            ->assertSee('To‘plangan ball 4.00 va maksimal ball 2.00 bo‘lsa')
            ->assertDontSee('Eski hisobot mezoni');

        $this->assertSame(1, substr_count($response->getContent(), 'data-testid="primary-indicator-row"'));

        $this->actingAs($user)
            ->get(route('upload.show', $inactiveCriterion))
            ->assertForbidden();
    }

    public function test_primary_indicator_codes_are_recognized_without_using_database_ids(): void
    {
        foreach (Criterion::PRIMARY_INDICATOR_CODES as $code) {
            $this->assertTrue((new Criterion(['code' => $code]))->isPrimaryIndicator());
        }

        $this->assertFalse((new Criterion(['code' => '2.1.1']))->isPrimaryIndicator());
    }

    public function test_home_displays_four_criterion_sections_as_tabs(): void
    {
        $user = User::factory()->withRole('user')->create(['degree' => 'hold_degrees']);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol hisobot'],
            'status' => '1',
        ]);

        for ($sectionNumber = 1; $sectionNumber <= 4; $sectionNumber++) {
            $parent = $this->createCriterion($report, [
                'name' => ['uz' => $sectionNumber.'-bo‘lim'],
                'sort_order' => $sectionNumber,
            ]);
            $criterion = $this->createCriterion($report, [
                'code' => $sectionNumber.'.1',
                'name' => ['uz' => $sectionNumber.'-bo‘lim mezoni'],
                'parent_id' => $parent->getKey(),
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => 'hold_degrees',
                'has' => '1',
                'score' => 5,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk();

        $this->assertSame(4, substr_count($response->getContent(), 'data-testid="criterion-section-tab"'));
        $this->assertSame(4, substr_count($response->getContent(), 'data-testid="criterion-section-pane"'));
        $this->assertSame(4, substr_count($response->getContent(), 'data-toggle="tab"'));
        $this->assertSame(1, substr_count($response->getContent(), 'aria-selected="true"'));
        $this->assertSame(3, substr_count($response->getContent(), 'aria-selected="false"'));
        $this->assertSame(1, substr_count($response->getContent(), 'tab-pane fade show active'));
    }

    public function test_international_project_criterion_is_uploadable_for_every_category(): void
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
            'code' => '2.1.4',
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
        DB::table('criterion_years')->insert([
            'criterion_id' => $criterionWithoutEvaluation->id,
            'year_id' => $year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('2.1.4')
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
