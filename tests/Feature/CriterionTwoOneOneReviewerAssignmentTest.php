<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CriterionTwoOneOneReviewerAssignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_future_human_reviews_use_the_requested_reviewer(): void
    {
        $criterion = new Criterion(['code' => '2.1.1']);

        $this->assertSame(
            3462011207,
            AiHumanReviewAssignment::reviewerHemisIdFor($criterion),
        );
    }

    public function test_assignment_command_reassigns_only_pending_two_one_one_human_reviews(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011207]);
        $oldReviewer = User::factory()->create();
        $owner = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Joriy KPI hisoboti'],
            'status' => '1',
        ]);
        $criterion = $this->createCriterion($report, '2.1.1');
        $otherCriterion = $this->createCriterion($report, '2.1.2');
        $pendingHumanReview = $this->createPendingDatum(
            $owner,
            $criterion,
            $oldReviewer->hemis_id,
        );
        $otherCriterionDatum = $this->createPendingDatum(
            $owner,
            $otherCriterion,
            $oldReviewer->hemis_id,
        );
        $aiFailure = $this->createPendingDatum(
            $owner,
            $criterion,
            $oldReviewer->hemis_id,
        );
        $aiFailure->histories()->create([
            'user_id' => $owner->getKey(),
            'type' => 'warning',
            'message' => 'AI tekshiruvida texnik xato.',
            'message_type' => 'ai_failed',
        ]);

        $this->artisan('kpi:ai:assign-human-reviews', [
            '--criterion' => '2.1.1',
            '--reassign' => true,
            '--dry-run' => true,
        ])->expectsOutput('AI inson tekshiruvi uchun biriktiriladigan resurslar: 1')
            ->assertSuccessful();
        $this->assertSame($oldReviewer->hemis_id, $pendingHumanReview->fresh()->reviewer_hemis_id);

        $this->artisan('kpi:ai:assign-human-reviews', [
            '--criterion' => '2.1.1',
            '--reassign' => true,
        ])->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 1')
            ->assertSuccessful();

        $this->assertSame($reviewer->hemis_id, $pendingHumanReview->fresh()->reviewer_hemis_id);
        $this->assertSame($oldReviewer->hemis_id, $otherCriterionDatum->fresh()->reviewer_hemis_id);
        $this->assertSame($oldReviewer->hemis_id, $aiFailure->fresh()->reviewer_hemis_id);
        $this->assertSame(1, $pendingHumanReview->histories()
            ->where('message_type', 'ai_human_review_assigned')
            ->count());

        $this->artisan('kpi:ai:assign-human-reviews', [
            '--criterion' => '2.1.1',
            '--reassign' => true,
        ])->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 0')
            ->assertSuccessful();
    }

    private function createCriterion(Report $report, string $code): Criterion
    {
        return Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code.' mezoni'],
            'report_id' => $report->getKey(),
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createPendingDatum(User $owner, Criterion $criterion, int $reviewerHemisId): Datum
    {
        $datum = Datum::query()->create([
            'name' => $criterion->code.' inson tekshiruvi',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewerHemisId,
        ]);
        $datum->histories()->create([
            'user_id' => $owner->getKey(),
            'type' => 'warning',
            'message' => 'AI inson tekshiruviga yubordi.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }
}
