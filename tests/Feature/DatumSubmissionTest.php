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
use RuntimeException;
use Tests\TestCase;

class DatumSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_file_submission_is_stored_privately_and_queued(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'res_type' => 'file',
            'checking' => 'ai',
            'ai_prompt' => 'Resursni %pointing% ballgacha baholang.',
            'ai_model' => 'gemini-test',
        ]);
        $year = $this->createActiveYear();
        Queue::fake();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                'year' => $year->id,
            ])
            ->assertRedirect(route('upload.show', $criterion));

        $datum = Datum::query()->sole();

        $this->assertSame('checking', $datum->status);
        $this->assertSame('local', $datum->storageDisk());
        $this->assertSame('application/pdf', data_get($datum->material, 'mime'));
        Storage::disk('local')->assertExists($datum->storagePath());
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'submission_created',
        ]);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $datum->id,
        );

        $this->actingAs($teacher)
            ->get(route('upload.file.download', $datum))
            ->assertDownload('proof.pdf');
    }

    public function test_h_index_submission_can_be_created_with_single_profile(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'code' => Criterion::H_INDEX_CODE,
            'checking' => 'manual',
        ]);
        $year = $this->createActiveYear();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'h_index',
                'year' => $year->id,
                'h_index' => [
                    'scopus' => [
                        'link' => 'https://www.scopus.com/user',
                        'value' => 12,
                    ],
                ],
            ])
            ->assertRedirect(route('upload.show', $criterion))
            ->assertSessionHasNoErrors();

        $datum = Datum::query()->sole();

        $this->assertSame('h_index', data_get($datum->material, 'type'));
        $this->assertArrayHasKey('scopus', data_get($datum->material, 'profiles'));
        $this->assertSame('https://www.scopus.com/user', data_get($datum->material, 'profiles.scopus.link'));
        $this->assertSame(12, data_get($datum->material, 'profiles.scopus.value'));
    }

    public function test_h_index_submission_requires_at_least_one_complete_profile(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'code' => Criterion::H_INDEX_CODE,
            'checking' => 'manual',
        ]);
        $year = $this->createActiveYear();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'h_index',
                'year' => $year->id,
                'h_index' => [],
            ])
            ->assertSessionHasErrors('h_index');
    }

    public function test_h_index_submission_rejects_partial_profile_data(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'code' => Criterion::H_INDEX_CODE,
            'checking' => 'manual',
        ]);
        $year = $this->createActiveYear();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'h_index',
                'year' => $year->id,
                'h_index' => [
                    'scopus' => [
                        'link' => 'https://www.scopus.com/user',
                    ],
                ],
            ])
            ->assertSessionHasErrors('h_index.scopus.value');
    }

    public function test_submission_resource_type_and_active_year_are_enforced(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion(['res_type' => 'url']);
        $inactiveYear = Year::query()->create([
            'id' => 2025,
            'name' => '2025',
            'status' => '0',
        ]);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                'year' => $inactiveYear->id,
            ])
            ->assertSessionHasErrors(['uploadResourceType', 'year']);

        $this->assertDatabaseCount('data', 0);
    }

    public function test_submission_year_must_be_assigned_to_the_criterion(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion(['res_type' => 'url']);
        $assignedYear = $this->createActiveYear();
        $otherYear = Year::query()->create([
            'id' => 2027,
            'name' => '2027',
            'status' => '1',
        ]);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/resource',
                'year' => $otherYear->id,
            ])
            ->assertSessionHasErrors('year');

        $this->assertDatabaseMissing('data', [
            'criterion_id' => $criterion->id,
            'year_id' => $otherYear->id,
        ]);
        $this->assertDatabaseHas('criterion_years', [
            'criterion_id' => $criterion->id,
            'year_id' => $assignedYear->id,
        ]);
    }

    public function test_ai_queue_dispatch_failure_is_recorded_for_status_monitoring(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'res_type' => 'url',
            'checking' => 'ai',
            'ai_prompt' => 'Tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        $year = $this->createActiveYear();
        Queue::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('Queue unavailable'));

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/resource',
                'year' => $year->id,
            ])
            ->assertRedirect();

        $datum = Datum::query()->sole();

        $this->assertSame('checking', $datum->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_failed',
            'message' => 'AI tekshiruvi navbatga qo‘yilmadi. Queue ulanishi yoki worker sozlamasi tekshirilishi kerak.',
        ]);
    }

    public function test_file_limit_is_rechecked_when_submission_is_created(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'res_type' => 'url',
            'file_limit' => 1,
        ]);
        $year = $this->createActiveYear();
        Datum::query()->create([
            'name' => 'Old URL',
            'material' => ['type' => 'url', 'link' => 'https://example.com/old'],
            'user_id' => $teacher->id,
            'criterion_id' => $criterion->id,
            'year_id' => $year->id,
            'status' => 'received',
        ]);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/new',
                'year' => $year->id,
            ])
            ->assertSessionHasErrors('uploadResourceFile');

        $this->assertDatabaseCount('data', 1);
    }

    public function test_cancelled_submission_does_not_consume_file_limit(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'res_type' => 'url',
            'file_limit' => 1,
        ]);
        $year = $this->createActiveYear();
        Datum::query()->create([
            'name' => 'Qaytarilgan URL',
            'material' => ['type' => 'url', 'link' => 'https://example.com/rejected'],
            'user_id' => $teacher->id,
            'criterion_id' => $criterion->id,
            'year_id' => $year->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($teacher)
            ->get(route('upload.show', $criterion))
            ->assertOk()
            ->assertSee('id="fileForm"', false);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/replacement',
                'year' => $year->id,
            ])
            ->assertRedirect(route('upload.show', $criterion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('data', 2);
        $this->assertDatabaseHas('data', [
            'criterion_id' => $criterion->id,
            'user_id' => $teacher->id,
            'status' => 'received',
            'name' => 'URL havola',
        ]);
    }

    public function test_manual_url_submission_is_received_without_ai_job(): void
    {
        $teacher = User::factory()->create();
        $criterion = $this->createCriterion([
            'res_type' => 'url',
            'checking' => 'manual',
        ]);
        $year = $this->createActiveYear();
        Queue::fake();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/resource',
                'year' => $year->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('data', [
            'criterion_id' => $criterion->id,
            'user_id' => $teacher->id,
            'status' => 'received',
        ]);
        Queue::assertNotPushed(ProcessAiDatumEvaluation::class);
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(array $attributes = []): Criterion
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);

        $criterion = Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'desc' => ['uz' => 'Test mezoni tavsifi'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'res_type' => 'all',
            'checking' => 'manual',
            'template' => '0',
        ], $attributes));

        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 10,
        ]);

        return $criterion;
    }

    private function createActiveYear(): Year
    {
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);

        DB::table('criterion_years')->insert([
            'criterion_id' => Criterion::query()->latest('id')->value('id'),
            'year_id' => $year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $year;
    }
}
