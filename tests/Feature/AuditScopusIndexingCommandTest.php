<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditScopusIndexingCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_finds_normalized_titles_without_changing_data(): void
    {
        [$report, $criterion, $user] = $this->fixture();
        $indexed = $this->datum($criterion, $user, 2026, 'Eco-friendly polymer formulations for wind erosion suppression in arid areas');
        $notIndexed = $this->datum($criterion, $user, 2026, 'Article absent from the Scopus list');
        $this->fakeReferences();

        $this->artisan('kpi:criteria:audit-3-1-3-indexing', ['report' => $report->getKey()])
            ->expectsOutputToContain('Scopus PDFda topildi: 1')
            ->expectsOutputToContain('Scopus PDFda topilmadi: 1')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('accepted', $indexed->fresh()->status);
        $this->assertSame('accepted', $notIndexed->fresh()->status);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    public function test_apply_rejects_only_unindexed_resources_and_is_idempotent(): void
    {
        [$report, $criterion, $user] = $this->fixture();
        $indexed = $this->datum($criterion, $user, 2026, 'Eco-friendly polymer formulations for wind erosion suppression in arid areas');
        $notIndexed = $this->datum($criterion, $user, 2026, 'Article absent from the Scopus list');
        $unsearchable = $this->datum($criterion, $user, 2026, null);
        $cancelled = $this->datum($criterion, $user, 2026, 'Another absent article', 'cancelled');
        $outsideYears = $this->datum($criterion, $user, 2027, 'Article absent from the Scopus list');
        $otherReport = Report::query()->create(['name' => ['uz' => 'Boshqa hisobot'], 'status' => '1']);
        $otherCriterion = $this->criterion($otherReport);
        $outsideReport = $this->datum($otherCriterion, $user, 2026, 'Article absent from the Scopus list');
        $this->fakeReferences();

        $this->artisan('kpi:criteria:audit-3-1-3-indexing', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->expectsOutputToContain('Rad etildi: 1')->assertSuccessful();

        $this->assertSame('accepted', $indexed->fresh()->status);
        $this->assertSame('cancelled', $notIndexed->fresh()->status);
        $this->assertSame(0.0, $notIndexed->fresh()->point);
        $this->assertSame('Maqola Scopus bazasida indekslanmagan', $notIndexed->fresh()->reason);
        $this->assertSame('accepted', $unsearchable->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('accepted', $outsideYears->fresh()->status);
        $this->assertSame('accepted', $outsideReport->fresh()->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $notIndexed->getKey(),
            'message' => 'Maqola Scopus bazasida indekslanmagan',
            'message_type' => 'scopus_index_reference_rejected',
        ]);

        $this->artisan('kpi:criteria:audit-3-1-3-indexing', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->expectsOutputToContain('Rad etildi: 0')->assertSuccessful();

        $this->assertDatabaseCount('datum_histories', 1);
    }

    public function test_unreadable_pdf_fails_without_changing_data(): void
    {
        [$report, $criterion, $user] = $this->fixture();
        $datum = $this->datum($criterion, $user, 2025, 'Article absent from the Scopus list');
        Storage::fake('local');
        Storage::disk('local')->put('imports/criterion-3.1.3/2025/scopus.pdf', "%PDF-1.4\n");
        Process::fake([
            '*' => Process::result(errorOutput: 'Unreadable PDF', exitCode: 1),
        ]);

        $this->artisan('kpi:criteria:audit-3-1-3-indexing', [
            'report' => $report->getKey(),
            '--apply' => true,
        ])->expectsOutputToContain('PDF matnini o‘qib bo‘lmadi')->assertFailed();

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertDatabaseCount('datum_histories', 0);
    }

    /** @return array{Report, Criterion, User} */
    private function fixture(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Scopus auditi'],
            'status' => '1',
        ]);
        $criterion = $this->criterion($report);
        $user = User::factory()->create();

        foreach ([2025, 2026, 2027] as $year) {
            Year::query()->create([
                'id' => $year,
                'name' => $year.'-'.($year + 1),
                'status' => '1',
            ]);
        }

        return [$report, $criterion, $user];
    }

    private function criterion(Report $report): Criterion
    {
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Unlimited],
            ['name' => ['uz' => 'Cheklanmagan'], 'status' => '1'],
        );

        return Criterion::query()->create([
            'code' => '3.1.3',
            'name' => ['uz' => 'Scopus maqolalari'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function datum(
        Criterion $criterion,
        User $user,
        int $year,
        ?string $title,
        string $status = 'accepted',
    ): Datum {
        return Datum::query()->create([
            'name' => $title ?? 'Eski fayl.pdf',
            'material' => [
                'type' => 'file',
                'path' => 'evidence.pdf',
                'article' => array_filter(['name' => $title]),
            ],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year,
            'status' => $status,
            'point' => 10,
        ]);
    }

    private function fakeReferences(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/criterion-3.1.3/2025/scopus-1.pdf', "%PDF-1.4\n");
        Storage::disk('local')->put('imports/criterion-3.1.3/2025/scopus-2.pdf', "%PDF-1.4\n");
        Storage::disk('local')->put('imports/criterion-3.1.3/2026/scopus-1.pdf', "%PDF-1.4\n");
        Process::fake([
            '*2025*2.pdf*' => str_repeat('Scopus reference text ', 10)
                ."ECO-FRIENDLY polymer formulations\nfor wind erosion suppression in arid areas",
            '*' => str_repeat('Different indexed publications ', 10),
        ]);
    }
}
