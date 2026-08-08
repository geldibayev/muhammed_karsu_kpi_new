<?php

namespace Tests\Feature;

use App\Actions\AssignDisciplinaryCriterionScore;
use App\Actions\SyncHemisWorkplaces;
use App\Actions\SyncHemisWorkplacesForLogin;
use App\Data\HemisWorkplaceSyncResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\DisciplinarySanction;
use App\Models\DisciplinarySanctionImport;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use App\Support\XlsxWriter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class DisciplinaryCriterionScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_import_assigns_zero_or_two_and_keeps_unknown_hemis_ids_for_future_login(): void
    {
        $criterion = $this->criterion();
        $sanctionedUser = User::factory()->create(['hemis_id' => 3462411069]);
        $cleanUser = User::factory()->create(['hemis_id' => 3461812009]);
        $futureHemisId = '3462211306';
        $this->storeXlsx('hemis_id.xlsx', [
            ['Jandos', '3462411069'],
            ['Azamat', $futureHemisId],
        ]);

        $this->artisan('kpi:discipline:import')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertDatabaseCount('disciplinary_sanction_imports', 0);
        $this->assertDatabaseCount('data', 0);

        $this->artisan('kpi:discipline:import', ['--apply' => true])
            ->expectsOutputToContain('APPLIED')
            ->assertSuccessful();

        $this->assertDatabaseCount('disciplinary_sanctions', 2);
        $this->assertDatabaseHas('disciplinary_sanctions', ['hemis_id' => $futureHemisId]);
        $this->assertScore($sanctionedUser, $criterion, 0);
        $this->assertScore($cleanUser, $criterion, 2);
        $this->assertSame(0.0, $this->datum($sanctionedUser, $criterion)->point);
        $this->assertSame(2.0, $this->datum($cleanUser, $criterion)->point);

        $futureUser = User::factory()->create(['hemis_id' => (int) $futureHemisId]);
        app(AssignDisciplinaryCriterionScore::class)->handle($futureUser);

        $this->assertScore($futureUser, $criterion, 0);
        $this->assertSame(0.0, $this->datum($futureUser, $criterion)->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $this->datum($futureUser, $criterion)->getKey(),
            'user_id' => $futureUser->getKey(),
            'message_type' => 'disciplinary_score_assigned',
        ]);
    }

    public function test_new_authoritative_snapshot_rescores_users_and_reimport_is_idempotent(): void
    {
        $criterion = $this->criterion();
        $user = User::factory()->create(['hemis_id' => 3462411069]);
        $this->storeXlsx('first.xlsx', [['Jandos', '3462411069']]);

        $this->artisan('kpi:discipline:import', [
            '--file' => 'first.xlsx',
            '--apply' => true,
        ])->assertSuccessful();
        $datum = $this->datum($user, $criterion);
        $this->assertSame(0.0, $datum->point);
        $historyCount = $datum->histories()->count();

        $this->artisan('kpi:discipline:import', [
            '--file' => 'first.xlsx',
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame(1, DisciplinarySanctionImport::query()->count());
        $this->assertSame($historyCount, $datum->histories()->count());

        $this->storeXlsx('second.xlsx', [['Boshqa shaxs', '3461812009']]);
        $this->artisan('kpi:discipline:import', [
            '--file' => 'second.xlsx',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, DisciplinarySanctionImport::query()->count());
        $this->assertDatabaseMissing('disciplinary_sanctions', ['hemis_id' => '3462411069']);
        $this->assertSame(2.0, $datum->fresh()->point);
        $this->assertScore($user, $criterion, 2);
    }

    public function test_import_rejects_duplicate_or_invalid_hemis_ids_without_replacing_snapshot(): void
    {
        $this->criterion();
        $this->storeXlsx('valid.xlsx', [['Jandos', '3462411069']]);
        $this->artisan('kpi:discipline:import', [
            '--file' => 'valid.xlsx',
            '--apply' => true,
        ])->assertSuccessful();

        $this->storeXlsx('duplicate.xlsx', [
            ['Jandos', '3462411069'],
            ['Jandos', '3462411069'],
        ]);
        $this->artisan('kpi:discipline:import', [
            '--file' => 'duplicate.xlsx',
            '--apply' => true,
        ])->assertFailed();

        $this->storeXlsx('invalid.xlsx', [['Noto‘g‘ri', 'ABC123']]);
        $this->artisan('kpi:discipline:import', [
            '--file' => 'invalid.xlsx',
            '--apply' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('disciplinary_sanction_imports', 1);
        $this->assertDatabaseHas('disciplinary_sanctions', ['hemis_id' => '3462411069']);
    }

    public function test_no_score_is_assigned_until_a_complete_snapshot_exists_and_system_score_cannot_be_deleted(): void
    {
        $criterion = $this->criterion();
        $user = User::factory()->create();

        $result = app(AssignDisciplinaryCriterionScore::class)->handle($user);
        $this->assertFalse($result['ready']);
        $this->assertDatabaseCount('data', 0);

        DisciplinarySanctionImport::factory()->create();
        app(AssignDisciplinaryCriterionScore::class)->handle($user);
        $datum = $this->datum($user, $criterion);

        $this->actingAs($user)
            ->delete(route('upload.destroy', $datum))
            ->assertForbidden();
        $this->assertModelExists($datum);
    }

    public function test_hemis_login_sync_assigns_stored_disciplinary_score(): void
    {
        $criterion = $this->criterion();
        $user = User::factory()->create(['hemis_id' => 3462211306]);
        $import = DisciplinarySanctionImport::factory()->create();
        DisciplinarySanction::factory()->create([
            'hemis_id' => (string) $user->hemis_id,
            'import_id' => $import->getKey(),
        ]);
        $this->mock(
            SyncHemisWorkplaces::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('handle')
                ->once()
                ->withArgs(fn (User $candidate): bool => $candidate->is($user))
                ->andReturn(new HemisWorkplaceSyncResult($user, false, 1)),
        );

        app(SyncHemisWorkplacesForLogin::class)->handle($user);

        $this->assertSame(0.0, $this->datum($user, $criterion)->point);
        $this->assertScore($user, $criterion, 0);
    }

    private function criterion(): Criterion
    {
        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            Evaluation::query()->firstOrCreate([
                'code' => $evaluation,
            ], [
                'name' => ['uz' => $evaluation],
                'status' => '1',
            ]);
        }
        $formula = Formula::query()->firstOrCreate([
            'code' => Formula::Maximum,
        ], [
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => '4.1.6 testi'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Intizom'],
            'report_id' => $report->getKey(),
            'upload' => '0',
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => AssignDisciplinaryCriterionScore::CRITERION_CODE,
            'name' => ['uz' => 'Intizomiy holat'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'site:disciplinary',
            'upload' => '0',
            'status' => '1',
        ]);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluation) {
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => 2,
            ]);
        }

        return $criterion;
    }

    /** @param  array<int, array{string, string}>  $rows */
    private function storeXlsx(string $filename, array $rows): void
    {
        $path = app(XlsxWriter::class)->write('Intizom', ['Ismi', 'hemis id'], $rows);

        try {
            Storage::disk('local')->put($filename, file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    private function datum(User $user, Criterion $criterion): Datum
    {
        return Datum::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($criterion)
            ->where('system_key', AssignDisciplinaryCriterionScore::SYSTEM_KEY)
            ->firstOrFail();
    }

    private function assertScore(User $user, Criterion $criterion, float $point): void
    {
        $this->assertSame($point, (float) Point::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($criterion)
            ->where('report_id', $criterion->report_id)
            ->value('point'));
    }
}
