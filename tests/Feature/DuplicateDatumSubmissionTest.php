<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicateDatumSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_same_file_is_blocked_before_a_second_ai_job_is_created(): void
    {
        Storage::fake('local');
        Queue::fake();
        $teacher = User::factory()->create();
        [$criterion, $year] = $this->criterionAndYear(['checking' => 'ai', 'res_type' => 'file']);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->createWithContent('first.pdf', 'identical document'),
                'year' => $year->getKey(),
            ])
            ->assertRedirect(route('upload.show', $criterion))
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->createWithContent('renamed.pdf', 'identical document'),
                'year' => $year->getKey(),
            ])
            ->assertSessionHasErrors('uploadResourceFile');

        $this->assertDatabaseCount('data', 1);
        $this->assertDatabaseCount('datum_resource_identifiers', 2);
        $this->assertDatabaseHas('datum_resource_identifiers', [
            'type' => 'file_sha256',
        ]);
        $this->assertCount(1, Storage::disk('local')->allFiles('uploads/kpi_resources'));
        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
    }

    public function test_same_doi_is_blocked_across_criteria_for_one_user_but_allowed_for_a_coauthor(): void
    {
        Storage::fake('local');
        Queue::fake();
        $teacher = User::factory()->create();
        $coauthor = User::factory()->create();
        [$firstCriterion, $year, $report] = $this->criterionAndYear(['res_type' => 'file']);
        $secondCriterion = $this->createCriterion($report, $year, ['res_type' => 'file']);

        $this->actingAs($teacher)
            ->post(route('upload.store', $firstCriterion), $this->articlePayload($year, 'first.pdf', 'first content'))
            ->assertRedirect(route('upload.show', $firstCriterion))
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)
            ->post(route('upload.store', $secondCriterion), $this->articlePayload($year, 'second.pdf', 'different content'))
            ->assertSessionHasErrors('uploadResourceFile');

        $this->actingAs($coauthor)
            ->post(route('upload.store', $secondCriterion), $this->articlePayload($year, 'coauthor.pdf', 'coauthor copy'))
            ->assertRedirect(route('upload.show', $secondCriterion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('data', 2);
        $this->assertSame(2, Datum::query()->distinct()->count('user_id'));
    }

    public function test_cancelled_resource_can_be_corrected_and_uploaded_again(): void
    {
        Queue::fake();
        $teacher = User::factory()->create();
        [$criterion, $year] = $this->criterionAndYear(['res_type' => 'url']);
        $payload = [
            'uploadResourceType' => 'url',
            'uploadResourceUrl' => 'https://example.com/resource?utm_source=test',
            'year' => $year->getKey(),
        ];

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $payload)
            ->assertSessionHasNoErrors();

        $firstDatum = Datum::query()->sole();
        $firstDatum->update(['status' => 'cancelled']);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                ...$payload,
                'uploadResourceUrl' => 'https://example.com/resource',
            ])
            ->assertRedirect(route('upload.show', $criterion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('data', 2);
        $this->assertDatabaseCount('datum_resource_identifiers', 2);
        $this->assertSame(1, DB::table('datum_resource_identifiers')->whereNotNull('active_value_hash')->count());
    }

    /** @return array{Criterion, Year, Report} */
    private function criterionAndYear(array $criterionAttributes = []): array
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);
        $criterion = $this->createCriterion($report, $year, $criterionAttributes);

        return [$criterion, $year, $report];
    }

    private function createCriterion(Report $report, Year $year, array $attributes = []): Criterion
    {
        $criterion = Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'desc' => ['uz' => 'Test tavsifi'],
            'report_id' => $report->getKey(),
            'upload' => '1',
            'status' => '1',
            'res_type' => 'all',
            'checking' => 'manual',
            'template' => '0',
        ], $attributes));
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 10,
        ]);
        DB::table('criterion_years')->insert([
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $criterion;
    }

    /** @return array<string, mixed> */
    private function articlePayload(Year $year, string $filename, string $contents): array
    {
        return [
            'uploadResourceType' => 'file',
            'uploadResourceFile' => UploadedFile::fake()->createWithContent($filename, $contents),
            'year' => $year->getKey(),
            'article' => [
                'name' => 'Duplicate-aware article',
                'journal' => 'Test Journal',
                'doi' => 'https://doi.org/10.1234/example.42',
            ],
        ];
    }
}
