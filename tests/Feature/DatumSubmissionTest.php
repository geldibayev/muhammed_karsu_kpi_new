<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);

        return Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'res_type' => 'all',
            'checking' => 'manual',
            'template' => '0',
        ], $attributes));
    }

    private function createActiveYear(): Year
    {
        return Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);
    }
}
