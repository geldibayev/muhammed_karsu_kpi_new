<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetryUnreadableAiImagesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_dry_run_reports_eligible_images_without_changing_or_dispatching_them(): void
    {
        $datum = $this->createDatum([
            'point' => 4,
            'reviewer_hemis_id' => 3172011004,
        ]);
        Queue::fake();

        $this->artisan('kpi:ai:retry-unreadable-images', ['--dry-run' => true])
            ->expectsOutput('Qayta AI tekshiruviga mos JPEG/PNG resurslar: 1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $datum->refresh();
        $this->assertSame(4.0, $datum->point);
        $this->assertSame(3172011004, $datum->reviewer_hemis_id);
        $this->assertSame(DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON, $datum->reason);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'ai_queued',
        ]);
    }

    public function test_command_requeues_eligible_human_review_image_once_and_skips_unrelated_resources(): void
    {
        $eligible = $this->createDatum([
            'point' => 4,
            'reviewer_hemis_id' => 3172011004,
        ]);
        $eligible->histories()->create([
            'user_id' => $eligible->user_id,
            'type' => 'warning',
            'message' => DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON,
            'message_type' => 'ai_evaluation',
        ]);
        $pdf = $this->createDatum(fileType: 'pdf');
        $manual = $this->createDatum(checking: 'manual');
        $otherFailure = $this->createDatum([
            'reason' => 'Boshqa AI xatosi.',
        ]);
        $accepted = $this->createDatum([
            'status' => 'accepted',
        ]);
        Queue::fake();

        $this->artisan('kpi:ai:retry-unreadable-images', ['--force' => true])
            ->expectsOutput('AI qayta tekshiruviga qo‘yildi: 1')
            ->assertSuccessful();
        $this->artisan('kpi:ai:retry-unreadable-images', ['--force' => true])
            ->expectsOutput('Qayta AI tekshiruviga mos JPEG/PNG resurs topilmadi.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $eligible->id
                && $job->criterionId === $eligible->criterion_id,
        );

        $eligible->refresh();
        $this->assertSame('checking', $eligible->status);
        $this->assertSame(0.0, $eligible->point);
        $this->assertNull($eligible->reviewer_hemis_id);
        $this->assertSame('Rasm fayli AI qayta tekshiruv navbatiga qo‘yildi.', $eligible->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $eligible->id,
            'type' => 'info',
            'message_type' => 'ai_queued',
        ]);

        foreach ([$pdf, $manual, $accepted] as $skippedDatum) {
            $this->assertSame(
                DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON,
                $skippedDatum->fresh()->reason,
            );
            $this->assertDatabaseMissing('datum_histories', [
                'datum_id' => $skippedDatum->id,
                'message_type' => 'ai_queued',
            ]);
        }

        $this->assertSame('Boshqa AI xatosi.', $otherFailure->fresh()->reason);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $otherFailure->id,
            'message_type' => 'ai_queued',
        ]);
    }

    public function test_command_requires_confirmation_without_force(): void
    {
        $datum = $this->createDatum();
        Queue::fake();

        $this->artisan('kpi:ai:retry-unreadable-images')
            ->expectsConfirmation('1 ta resursni AI qayta tekshiruviga yuborasizmi?', 'no')
            ->expectsOutput('Qayta navbatga qo‘yish bekor qilindi.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(
            DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON,
            $datum->fresh()->reason,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDatum(
        array $attributes = [],
        string $checking = 'ai',
        string $fileType = 'jpeg',
    ): Datum {
        $user = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Test mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'checking' => $checking,
            'ai_prompt' => 'Tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        $path = match ($fileType) {
            'pdf' => 'proof-'.Str::uuid().'.pdf',
            default => 'proof-'.Str::uuid().'.jpg',
        };

        if ($fileType === 'pdf') {
            Storage::disk('local')->put($path, "%PDF-1.4\n% test document");
        } else {
            $image = UploadedFile::fake()->image('proof.jpg', 10, 10);
            Storage::disk('local')->put($path, $image->getContent());
        }

        return Datum::query()->create(array_merge([
            'name' => basename($path),
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => $path,
                'mime' => 'application/pdf',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
            'reason' => DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON,
        ], $attributes));
    }
}
