<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiHumanReviewerStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_sees_latest_assignment_statistics(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $firstReviewer = User::factory()->create([
            'hemis_id' => 1111111111,
            'name' => $this->userName('Birinchi Mas’ul'),
        ]);
        $secondReviewer = User::factory()->create([
            'hemis_id' => 2222222222,
            'name' => $this->userName('Ikkinchi Mas’ul'),
        ]);
        $owner = User::factory()->create();
        $criterion = $this->criterion();

        $this->assignedDatum($owner, $criterion, $firstReviewer, 'checking', $firstReviewer->hemis_id);

        $approved = $this->assignedDatum($owner, $criterion, $firstReviewer, 'accepted');
        $this->decision($approved, $firstReviewer, 'manual_review_approved');

        $rejected = $this->assignedDatum($owner, $criterion, $firstReviewer, 'cancelled');
        $this->decision($rejected, $firstReviewer, 'manual_review_rejected');

        $reassigned = $this->assignedDatum($owner, $criterion, $firstReviewer, 'checking', $secondReviewer->hemis_id);
        $this->assignment($reassigned, $secondReviewer);

        $invalidated = $this->assignedDatum($owner, $criterion, $firstReviewer, 'checking');
        $invalidated->histories()->create([
            'user_id' => $owner->id,
            'type' => 'info',
            'message' => 'Resurs qayta AI navbatiga qo‘yildi.',
            'message_type' => 'ai_queued',
        ]);

        $malformed = Datum::query()->create([
            'name' => 'Noto‘g‘ri auditli resurs',
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'reviewer_hemis_id' => $firstReviewer->hemis_id,
            'status' => 'checking',
            'point' => 0,
        ]);
        $malformed->histories()->create([
            'user_id' => $owner->id,
            'type' => 'info',
            'message' => 'Mas’ul biriktirildi.',
            'message_type' => 'ai_human_review_assigned',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('AI mas’ullar statistikasi')
            ->assertSee(route('ai-human-reviewer-statistics.index'));

        $this->actingAs($superAdmin)
            ->get(route('ai-human-reviewer-statistics.index'))
            ->assertOk()
            ->assertSee('Birinchi Mas’ul')
            ->assertSee('Ikkinchi Mas’ul')
            ->assertSee('Tekshirilmagan')
            ->assertSee('Qabul qilingan')
            ->assertSee('Rad etilgan')
            ->assertViewHas('statistics', function (array $statistics): bool {
                $reviewers = collect($statistics['reviewers'])->keyBy('hemis_id');

                return $statistics['summary'] === [
                    'reviewers' => 2,
                    'total' => 4,
                    'checked' => 2,
                    'unchecked' => 2,
                    'approved' => 1,
                    'rejected' => 1,
                    'completion_rate' => 50.0,
                ]
                    && collect($reviewers->get(1111111111))->only([
                        'total', 'checked', 'unchecked', 'approved', 'rejected', 'completion_rate',
                    ])->all() === [
                        'total' => 3,
                        'checked' => 2,
                        'unchecked' => 1,
                        'approved' => 1,
                        'rejected' => 1,
                        'completion_rate' => 66.7,
                    ]
                    && collect($reviewers->get(2222222222))->only([
                        'total', 'checked', 'unchecked', 'approved', 'rejected', 'completion_rate',
                    ])->all() === [
                        'total' => 1,
                        'checked' => 0,
                        'unchecked' => 1,
                        'approved' => 0,
                        'rejected' => 0,
                        'completion_rate' => 0.0,
                    ];
            });
    }

    public function test_only_super_admin_can_see_the_menu_and_page(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '9999999999');
        $aiStatusViewer = User::factory()->create(['hemis_id' => 9999999999]);

        $this->actingAs($aiStatusViewer)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('ai-human-reviewer-statistics.index'));

        $this->actingAs($aiStatusViewer)
            ->get(route('ai-human-reviewer-statistics.index'))
            ->assertForbidden();

        auth()->logout();

        $this->get(route('ai-human-reviewer-statistics.index'))
            ->assertRedirect(route('login'));
    }

    public function test_empty_statistics_are_zero_safe(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('ai-human-reviewer-statistics.index'))
            ->assertOk()
            ->assertSee('AI inson tekshiruviga biriktirilgan resurslar topilmadi.')
            ->assertViewHas('statistics', fn (array $statistics): bool => $statistics === [
                'summary' => [
                    'reviewers' => 0,
                    'total' => 0,
                    'checked' => 0,
                    'unchecked' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'completion_rate' => 0.0,
                ],
                'reviewers' => [],
            ]);
    }

    private function criterion(): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'AI mas’ullar statistikasi'],
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'code' => 'test-ai',
            'name' => ['uz' => 'AI kriteriya'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function assignedDatum(
        User $owner,
        Criterion $criterion,
        User $reviewer,
        string $status,
        ?int $currentReviewerHemisId = null,
    ): Datum {
        $datum = Datum::query()->create([
            'name' => 'AI inson tekshiruvidagi resurs',
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'reviewer_hemis_id' => $currentReviewerHemisId,
            'status' => $status,
            'point' => 0,
        ]);
        $this->assignment($datum, $reviewer);

        return $datum;
    }

    private function assignment(Datum $datum, User $reviewer): void
    {
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'info',
            'message' => "AI inson tekshiruvi HEMIS ID {$reviewer->hemis_id} mas’ulga biriktirildi.",
            'message_type' => 'ai_human_review_assigned',
        ]);
    }

    private function decision(Datum $datum, User $reviewer, string $messageType): void
    {
        $datum->histories()->create([
            'user_id' => $reviewer->id,
            'type' => $messageType === 'manual_review_approved' ? 'success' : 'error',
            'message' => 'Inson tekshiruvi qarori.',
            'message_type' => $messageType,
        ]);
    }

    /** @return array{full: string, first: string, last: string, third: string, short: string} */
    private function userName(string $full): array
    {
        return [
            'full' => $full,
            'first' => $full,
            'last' => '',
            'third' => '',
            'short' => $full,
        ];
    }
}
