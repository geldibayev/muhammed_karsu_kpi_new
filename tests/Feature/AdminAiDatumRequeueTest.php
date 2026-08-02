<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminAiDatumRequeueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_primary_admin_sees_the_button_and_can_requeue_a_cancelled_ai_evaluation(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $administrator = User::factory()->withRole('super_admin')->create([
            'hemis_id' => 3172011004,
        ]);
        $datum = $this->createAiEvaluatedDatum();
        $datum->update([
            'point' => 3,
            'author_count' => 2,
            'page_count' => 10,
            'reviewer_hemis_id' => 123456,
        ]);
        Queue::fake();

        $this->actingAs($administrator)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('AI tekshiruviga qayta yuborish')
            ->assertSee(route('upload.ai-requeue', $datum));

        $this->actingAs($administrator)
            ->post(route('upload.ai-requeue', $datum))
            ->assertRedirect(route('upload.details', $datum))
            ->assertSessionHas('success', 'Resurs AI tekshiruviga qayta yuborildi.');

        $datum->refresh();
        $this->assertSame('checking', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertNull($datum->author_count);
        $this->assertNull($datum->page_count);
        $this->assertNull($datum->reviewer_hemis_id);
        $this->assertSame(Datum::PUBLIC_CHECKING_REASON, $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $administrator->id,
            'message_type' => 'ai_manual_recheck_queued',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $administrator->id,
            'message_type' => 'ai_queued',
        ]);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $datum->id
                && $job->criterionId === $datum->criterion_id,
        );
    }

    public function test_other_users_cannot_see_or_use_the_requeue_action(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $otherAdministrator = User::factory()->withRole('super_admin')->create([
            'hemis_id' => 9999999999,
        ]);
        $configuredTeacher = User::factory()->withRole('teacher')->create([
            'hemis_id' => 3172011004,
        ]);
        $datum = $this->createAiEvaluatedDatum();
        Queue::fake();

        $this->actingAs($otherAdministrator)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee('AI tekshiruviga qayta yuborish');

        $this->actingAs($otherAdministrator)
            ->post(route('upload.ai-requeue', $datum))
            ->assertForbidden();
        $this->actingAs($configuredTeacher)
            ->post(route('upload.ai-requeue', $datum))
            ->assertForbidden();

        $this->assertSame('cancelled', $datum->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_only_the_latest_unmodified_ai_rejection_can_be_requeued(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $administrator = User::factory()->withRole('super_admin')->create([
            'hemis_id' => 3172011004,
        ]);
        $accepted = $this->createAiEvaluatedDatum(status: 'accepted');
        $manualCriterion = $this->createCriterion(checking: 'manual');
        $manualCriterionDatum = $this->createAiEvaluatedDatum(criterion: $manualCriterion);
        $withoutAiHistory = $this->createDatum($this->createCriterion(), 'cancelled');
        $manuallyRejected = $this->createAiEvaluatedDatum();
        $manuallyRejected->histories()->create([
            'user_id' => $manuallyRejected->user_id,
            'type' => 'error',
            'message' => 'Mas’ul qaytardi.',
            'message_type' => 'manual_review_rejected',
        ]);
        Queue::fake();

        foreach ([$accepted, $manualCriterionDatum, $withoutAiHistory, $manuallyRejected] as $datum) {
            $this->actingAs($administrator)
                ->post(route('upload.ai-requeue', $datum))
                ->assertForbidden();
        }

        Queue::assertNothingPushed();
    }

    public function test_repeated_click_cannot_enqueue_the_same_ai_evaluation_twice(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $administrator = User::factory()->withRole('super_admin')->create([
            'hemis_id' => 3172011004,
        ]);
        $datum = $this->createAiEvaluatedDatum();
        Queue::fake();

        $this->actingAs($administrator)
            ->post(route('upload.ai-requeue', $datum))
            ->assertRedirect();
        $this->actingAs($administrator)
            ->post(route('upload.ai-requeue', $datum))
            ->assertForbidden();

        Queue::assertPushed(ProcessAiDatumEvaluation::class, 1);
    }

    private function createAiEvaluatedDatum(
        ?Criterion $criterion = null,
        string $status = 'cancelled',
    ): Datum {
        $criterion ??= $this->createCriterion();
        $datum = $this->createDatum($criterion, $status);
        $datum->histories()->createMany([
            [
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Resurs foydalanuvchi tomonidan yuborildi.',
                'message_type' => 'submission_created',
            ],
            [
                'user_id' => $datum->user_id,
                'type' => $status === 'accepted' ? 'success' : 'error',
                'message' => 'AI xulosasi.',
                'message_type' => 'ai_evaluation',
            ],
        ]);

        return $datum;
    }

    private function createCriterion(string $checking = 'ai'): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => fake()->sentence()],
            'report_id' => $report->id,
            'checking' => $checking,
            'ai_prompt' => $checking === 'ai' ? 'Hujjatni tekshiring.' : null,
            'ai_model' => 'gemini-test',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createDatum(Criterion $criterion, string $status): Datum
    {
        $submitter = User::factory()->withRole('teacher')->create();

        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'file', 'path' => fake()->uuid().'.pdf'],
            'user_id' => $submitter->id,
            'criterion_id' => $criterion->id,
            'status' => $status,
            'point' => $status === 'accepted' ? 3 : 0,
            'reason' => 'Oldingi xulosa.',
        ]);
    }
}
