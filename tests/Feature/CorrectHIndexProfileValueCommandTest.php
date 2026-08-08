<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CorrectHIndexProfileValueCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_corrects_research_gate_value_audits_and_recalculates_only_affected_report(): void
    {
        $owner = User::factory()->create([
            'hemis_id' => 3461511006,
            'degree' => 'hold_degrees',
        ]);
        $actor = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $criterion = $this->hIndexCriterion('Asosiy hisobot');
        $datum = $this->hIndexDatum($owner, $criterion, 9, 7);

        $otherOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $otherCriterion = $this->hIndexCriterion('Boshqa hisobot');
        $otherDatum = $this->hIndexDatum($otherOwner, $otherCriterion, 9, 7);
        Point::query()->create([
            'user_id' => $otherOwner->getKey(),
            'criterion_id' => $otherCriterion->getKey(),
            'report_id' => $otherCriterion->report_id,
            'point' => 99,
        ]);

        $exitCode = Artisan::call('kpi:h-index:correct', [
            'hemis_id' => '3461511006',
            '--actor' => '3172011004',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $datum->refresh();
        $this->assertSame(6, data_get($datum->material, 'profiles.research_gate.value'));
        $this->assertSame(4.0, $datum->point);
        $this->assertSame(4.0, (float) Point::query()
            ->where('user_id', $owner->getKey())
            ->where('criterion_id', $criterion->getKey())
            ->where('report_id', $criterion->report_id)
            ->value('point'));
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $actor->getKey(),
            'message_type' => 'h_index_profile_corrected',
        ]);
        $this->assertStringContainsString('9 dan 6 ga', (string) $datum->histories()
            ->where('message_type', 'h_index_profile_corrected')
            ->value('message'));

        $this->assertSame(7.0, $otherDatum->fresh()->point);
        $this->assertSame(99.0, (float) Point::query()
            ->where('user_id', $otherOwner->getKey())
            ->where('criterion_id', $otherCriterion->getKey())
            ->where('report_id', $otherCriterion->report_id)
            ->value('point'));

        $this->artisan('kpi:h-index:correct', [
            'hemis_id' => '3461511006',
            '--actor' => '3172011004',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'h_index_profile_corrected')
            ->count());
    }

    public function test_dry_run_does_not_change_data_and_missing_or_unexpected_data_fails(): void
    {
        $owner = User::factory()->create([
            'hemis_id' => 3461511006,
            'degree' => 'hold_degrees',
        ]);
        $datum = $this->hIndexDatum($owner, $this->hIndexCriterion('Dry run'), 9, 7);

        $this->artisan('kpi:h-index:correct', ['hemis_id' => '3461511006'])
            ->assertSuccessful();

        $this->assertSame(9, data_get($datum->fresh()->material, 'profiles.research_gate.value'));
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'h_index_profile_corrected',
        ]);

        $material = $datum->material;
        data_set($material, 'profiles.research_gate.value', 8);
        $datum->update(['material' => $material]);

        $this->artisan('kpi:h-index:correct', ['hemis_id' => '3461511006'])
            ->assertFailed();
        $this->artisan('kpi:h-index:correct', ['hemis_id' => '999999999'])
            ->assertFailed();
    }

    public function test_it_refuses_ambiguous_resources_until_datum_is_specified(): void
    {
        $owner = User::factory()->create([
            'hemis_id' => 3461511006,
            'degree' => 'hold_degrees',
        ]);
        $actor = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $criterion = $this->hIndexCriterion('Noaniq resurslar');
        $first = $this->hIndexDatum($owner, $criterion, 9, 7);
        $this->hIndexDatum($owner, $criterion, 9, 7);

        $this->artisan('kpi:h-index:correct', [
            'hemis_id' => '3461511006',
            '--actor' => (string) $actor->hemis_id,
            '--apply' => true,
        ])->assertFailed();

        $exitCode = Artisan::call('kpi:h-index:correct', [
            'hemis_id' => '3461511006',
            '--datum' => (string) $first->getKey(),
            '--actor' => (string) $actor->hemis_id,
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertSame(6, data_get($first->fresh()->material, 'profiles.research_gate.value'));
    }

    private function hIndexCriterion(string $reportName): Criterion
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'hold_degrees'],
            ['name' => ['uz' => 'Ilmiy darajali'], 'status' => '1'],
        );
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Maximum],
            ['name' => ['uz' => 'Maksimal'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => $reportName],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => $reportName.' bo‘limi'],
            'report_id' => $report->getKey(),
            'upload' => '0',
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => Criterion::H_INDEX_CODE,
            'name' => ['uz' => 'H-index'],
            'report_id' => $report->getKey(),
            'parent_id' => $parent->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 3,
        ]);

        return $criterion;
    }

    private function hIndexDatum(User $owner, Criterion $criterion, int $researchGate, float $point): Datum
    {
        return Datum::query()->create([
            'name' => 'H-index resursi '.fake()->uuid(),
            'material' => [
                'type' => 'h_index',
                'profiles' => [
                    'research_gate' => [
                        'link' => 'https://researchgate.example/'.fake()->uuid(),
                        'value' => $researchGate,
                    ],
                ],
            ],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }
}
