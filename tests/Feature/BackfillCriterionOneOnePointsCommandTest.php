<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BackfillCriterionOneOnePointsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_recalculates_legacy_points_records_types_and_is_idempotent(): void
    {
        [$report, $criterion, $options] = $this->criterionFixture();
        $noDegree = User::factory()->create(['degree' => 'no_degrees']);
        $withDegree = User::factory()->create(['degree' => 'hold_degrees']);
        $foreignLanguage = User::factory()->create(['degree' => 'foreign_lang']);
        $videoLesson = $this->acceptedDatum($noDegree, $criterion, 1.5);
        $videoLesson->histories()->create([
            'user_id' => $noDegree->getKey(),
            'type' => 'success',
            'message' => 'Mas’ul tomonidan tasdiqlandi. Qoida: Videodars. Hisoblangan ball: 1.50.',
            'message_type' => 'manual_review_approved',
        ]);
        $videoClip = $this->acceptedDatum($withDegree, $criterion, 1.0);
        $presentation = $this->acceptedDatum($foreignLanguage, $criterion, 0.5);

        $this->artisan('kpi:criteria:recalculate-1-1-points', ['report' => $report->getKey()])
            ->expectsOutput('1.1 bo‘yicha accepted resurslar: 3')
            ->expectsOutput('Yangilanishi kerak: 3')
            ->expectsOutput('Aniqlanmagan resurslar: 0')
            ->assertSuccessful();

        $this->assertSame(1.5, $videoLesson->fresh()->point);
        $this->assertNull($videoLesson->fresh()->manual_score_option_id);

        $this->artisan('kpi:criteria:recalculate-1-1-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(3.0, $videoLesson->fresh()->point);
        $this->assertSame($options['video_lesson']->getKey(), $videoLesson->fresh()->manual_score_option_id);
        $this->assertSame(1.2, $videoClip->fresh()->point);
        $this->assertSame($options['video_clip']->getKey(), $videoClip->fresh()->manual_score_option_id);
        $this->assertSame(0.4, $presentation->fresh()->point);
        $this->assertSame($options['presentation']->getKey(), $presentation->fresh()->manual_score_option_id);
        $this->assertDatabaseCount('datum_histories', 4);
        $this->assertSame(3.0, (float) Point::query()
            ->whereBelongsTo($noDegree)
            ->whereBelongsTo($criterion)
            ->value('point'));

        $this->artisan('kpi:criteria:recalculate-1-1-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->expectsOutput('1.1 bo‘yicha yangilandi: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('datum_histories', 4);
    }

    public function test_apply_refuses_all_updates_when_any_resource_type_is_unresolved(): void
    {
        [$report, $criterion] = $this->criterionFixture();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $resolvable = $this->acceptedDatum($owner, $criterion, 1.5);
        $unresolved = $this->acceptedDatum($owner, $criterion, 0.7);

        $this->artisan('kpi:criteria:recalculate-1-1-points', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->expectsOutput('Aniqlanmagan resurslar: 1')
            ->expectsOutput('Resurs turi aniqlanmagan ID lar: '.$unresolved->getKey())
            ->assertFailed();

        $this->assertSame(1.5, $resolvable->fresh()->point);
        $this->assertNull($resolvable->fresh()->manual_score_option_id);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    /** @return array{Report, Criterion, array<string, CriterionManualScoreOption>} */
    private function criterionFixture(): array
    {
        $report = Report::query()->create(['name' => ['uz' => 'Hisobot'], 'status' => '1']);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ball'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
        ]);
        $criterion = Criterion::query()->create([
            'code' => '1.1',
            'name' => ['uz' => 'O‘quv kontenti'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'file_limit' => 3,
            'status' => '1',
        ]);

        foreach (['hold_degrees' => 3, 'no_degrees' => 6, 'foreign_lang' => 4, 'physical' => 4] as $category => $score) {
            Evaluation::query()->firstOrCreate(
                ['code' => $category],
                ['name' => ['uz' => $category], 'status' => '1'],
            );
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $category,
                'has' => '1',
                'score' => $score,
            ]);
        }

        $options = collect([
            'video_lesson' => 1.5,
            'video_clip' => 1.0,
            'presentation' => 0.5,
        ])->mapWithKeys(function (float $point, string $code) use ($criterion): array {
            $option = CriterionManualScoreOption::query()->create([
                'criterion_id' => $criterion->getKey(),
                'code' => $code,
                'label' => ['uz' => $code],
                'point' => $point,
                'active' => true,
            ]);

            return [$code => $option];
        })->all();

        return [$report, $criterion, $options];
    }

    private function acceptedDatum(User $owner, Criterion $criterion, float $point): Datum
    {
        return Datum::query()->create([
            'name' => 'Eski resurs',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }
}
