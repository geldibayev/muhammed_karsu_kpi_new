<?php

namespace Tests\Feature;

use App\Actions\RecalculateLaboratoryWorkEvaluations;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Support\LaboratoryWorkCriterionRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculateLaboratoryWorkEvaluationsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_does_not_change_or_dispatch_resources(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $known = $this->acceptedDatum($criterion, ['author_count' => 2, 'point' => 1]);
        $unknown = $this->acceptedDatum($criterion, ['author_count' => null, 'reason' => 'Eski tasdiq.']);

        $this->artisan('kpi:recalculate-criterion-1-8', ['report' => $report->id])
            ->expectsOutputToContain('Qayta hisoblanadi: 1')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame(1.0, $known->fresh()->point);
        $this->assertSame('accepted', $unknown->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_apply_recalculates_unambiguous_legacy_counts_and_requeues_unknown_or_conflicting_rows(): void
    {
        Queue::fake();
        [$report, $criterion] = $this->context();
        $structured = $this->acceptedDatum($criterion, ['author_count' => 2, 'point' => 1]);
        $material = $this->acceptedDatum($criterion, [
            'material' => ['type' => 'file', 'path' => 'legacy.pdf', 'article' => ['authors_num' => 4]],
            'author_count' => null,
            'point' => 1,
        ]);
        $unknown = $this->acceptedDatum($criterion, ['author_count' => null, 'point' => 1, 'reason' => 'Eski tasdiq.']);
        $conflict = $this->acceptedDatum($criterion, [
            'author_count' => 2,
            'point' => 1,
            'reason' => 'Jami mualliflar soni: 3.',
        ]);
        $unchanged = $this->acceptedDatum($criterion, ['author_count' => 1, 'point' => 0.5]);

        $this->artisan('kpi:recalculate-criterion-1-8', [
            'report' => $report->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Qayta hisoblandi: 2')
            ->assertSuccessful();

        $this->assertSame(0.25, $structured->fresh()->point);
        $this->assertSame(2, $structured->fresh()->author_count);
        $this->assertSame(0.125, $material->fresh()->point);
        $this->assertSame(4, $material->fresh()->author_count);
        $this->assertSame(0.5, $unchanged->fresh()->point);

        foreach ([$unknown, $conflict] as $datum) {
            $datum->refresh();
            $this->assertSame('checking', $datum->status);
            $this->assertSame(0.0, $datum->point);
            $this->assertNull($datum->author_count);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->id,
                'message_type' => RecalculateLaboratoryWorkEvaluations::RECHECK_HISTORY_TYPE,
            ]);
        }

        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $structured->id,
            'message_type' => RecalculateLaboratoryWorkEvaluations::RECALCULATED_HISTORY_TYPE,
        ]);
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);

        $this->artisan('kpi:recalculate-criterion-1-8', [
            'report' => $report->id,
            '--apply' => true,
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 2);
    }

    public function test_command_rejects_unknown_report_and_invalid_limit(): void
    {
        $this->artisan('kpi:recalculate-criterion-1-8', ['report' => 999999])
            ->expectsOutputToContain('Hisobot topilmadi')
            ->assertFailed();

        [$report] = $this->context();

        $this->artisan('kpi:recalculate-criterion-1-8', [
            'report' => $report->id,
            '--limit' => 0,
        ])
            ->expectsOutputToContain('--limit musbat butun son')
            ->assertFailed();
    }

    /** @return array{Report, Criterion} */
    private function context(): array
    {
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Maximum],
            ['name' => ['uz' => 'Maksimum'], 'status' => '1'],
        );
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $criterion = Criterion::query()->create([
            'code' => LaboratoryWorkCriterionRule::CODE,
            'name' => ['uz' => 'Laboratoriya ishlari'],
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'ai',
            'ai_prompt' => LaboratoryWorkCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);

        return [$report, $criterion];
    }

    /** @param array<string, mixed> $overrides */
    private function acceptedDatum(Criterion $criterion, array $overrides = []): Datum
    {
        return Datum::query()->create(array_merge([
            'name' => 'Laboratoriya resursi',
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => User::factory()->create()->id,
            'criterion_id' => $criterion->id,
            'status' => 'accepted',
            'point' => 1,
            'reason' => 'Eski AI xulosasi.',
        ], $overrides));
    }
}
