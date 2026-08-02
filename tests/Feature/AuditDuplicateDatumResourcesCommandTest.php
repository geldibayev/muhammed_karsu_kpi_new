<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditDuplicateDatumResourcesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_duplicates_and_apply_invalidates_them_idempotently(): void
    {
        [$user, $report, $criterion, $year] = $this->scoringContext();
        $lowerScored = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/article?utm_source=first',
            2,
        );
        $canonical = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/article',
            3,
        );

        $this->artisan('kpi:duplicates:audit', ['--report' => $report->getKey()])
            ->expectsOutputToContain('Ortiqcha resurslar: 1')
            ->expectsOutputToContain('Dry-run yakunlandi')
            ->assertSuccessful();

        $this->assertSame('accepted', $lowerScored->fresh()->status);
        $this->assertDatabaseCount('datum_resource_identifiers', 0);

        $this->artisan('kpi:duplicates:audit', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('data', [
            'id' => $canonical->getKey(),
            'status' => 'accepted',
            'point' => 3,
            'duplicate_of_id' => null,
        ]);
        $this->assertDatabaseHas('data', [
            'id' => $lowerScored->getKey(),
            'status' => 'deleted',
            'point' => 0,
            'duplicate_of_id' => $canonical->getKey(),
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $lowerScored->getKey(),
            'message_type' => 'duplicate_resource_invalidated',
        ]);
        $this->assertDatabaseHas('points', [
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 3,
        ]);
        $this->assertGreaterThan(0, $canonical->resourceIdentifiers()->whereNotNull('active_value_hash')->count());
        $this->assertGreaterThan(0, $lowerScored->resourceIdentifiers()->whereNull('active_value_hash')->count());

        $identifierCount = $canonical->resourceIdentifiers()->count()
            + $lowerScored->resourceIdentifiers()->count();

        $this->artisan('kpi:duplicates:audit', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($identifierCount, $canonical->resourceIdentifiers()->count()
            + $lowerScored->resourceIdentifiers()->count());
        $this->assertSame(1, $lowerScored->histories()
            ->where('message_type', 'duplicate_resource_invalidated')
            ->count());
    }

    public function test_lowest_id_is_kept_when_duplicate_scores_are_equal(): void
    {
        [$user, $report, $criterion, $year] = $this->scoringContext();
        $canonical = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/equal-score',
            4,
        );
        $duplicate = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/equal-score?utm_source=second',
            4,
        );

        $this->artisan('kpi:duplicates:audit', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('accepted', $canonical->fresh()->status);
        $this->assertNull($canonical->fresh()->duplicate_of_id);
        $this->assertSame('deleted', $duplicate->fresh()->status);
        $this->assertSame($canonical->getKey(), $duplicate->fresh()->duplicate_of_id);
    }

    public function test_title_and_journal_match_is_only_reported_for_manual_review(): void
    {
        [$user, $report, $criterion, $year] = $this->scoringContext();
        $first = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/first',
            1,
            null,
        );
        $second = $this->createAcceptedDatum(
            $user,
            $criterion,
            $year,
            'https://example.com/second',
            1,
            null,
        );

        $this->artisan('kpi:duplicates:audit', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])
            ->expectsOutputToContain('Ortiqcha resurslar: 0')
            ->expectsOutputToContain('moderator ko‘rishi kerak bo‘lgan guruhlar: 1')
            ->assertSuccessful();

        $this->assertSame('accepted', $first->fresh()->status);
        $this->assertSame('accepted', $second->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'message_type' => 'duplicate_resource_invalidated',
        ]);
    }

    /** @return array{User, Report, Criterion, Year} */
    private function scoringContext(): array
    {
        $user = User::factory()->create(['degree' => 'no_degrees']);
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Dublikat auditi'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Maqola'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 10,
        ]);
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);

        return [$user, $report, $criterion, $year];
    }

    private function createAcceptedDatum(
        User $user,
        Criterion $criterion,
        Year $year,
        string $url,
        float $point,
        ?string $doi = '10.1234/duplicate.1',
    ): Datum {
        $article = [
            'name' => 'Bir xil ilmiy maqola',
            'journal' => 'Sinov jurnali',
        ];

        if ($doi !== null) {
            $article['doi'] = $doi;
        }

        return Datum::query()->create([
            'name' => 'URL havola',
            'material' => [
                'type' => 'url',
                'link' => $url,
                'article' => $article,
            ],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }
}
