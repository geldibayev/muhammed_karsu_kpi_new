<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AcceptedAiHumanRejectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_reviewer_sees_and_rejects_gemini_accepted_resource_with_reason(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->acceptedAiDatum($owner, $criterion, 3.5);
        CriterionPoint::query()->create([
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 3.5,
            'files' => 1,
        ]);
        Point::query()->create([
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 3.5,
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertDontSee('Gemini tasdiqlagan resurslar')
            ->assertDontSee($datum->name);

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Gemini tasdig‘ini bekor qilish')
            ->assertSee(route('ai-human-reviews.reject-accepted', $datum))
            ->assertDontSee(route('reviews.approve', $datum));

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), [
                'reason' => 'ISBN boshqa nashrga tegishli ekanligi aniqlandi.',
            ])
            ->assertRedirect(route('upload.details', $datum))
            ->assertSessionHasNoErrors();

        $datum->refresh();
        $this->assertSame('cancelled', $datum->status);
        $this->assertSame(0.0, $datum->point);
        $this->assertNull($datum->author_count);
        $this->assertNull($datum->page_count);
        $this->assertStringContainsString('ISBN boshqa nashrga tegishli', (string) $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $reviewer->getKey(),
            'type' => 'error',
            'message_type' => 'human_override_ai_rejected',
        ]);
        $this->assertDatabaseMissing('criterion_points', [
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
        ]);
        $this->assertDatabaseMissing('points', [
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
        ]);
    }

    public function test_reason_is_required_and_limited_without_mutating_resource(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->acceptedAiDatum($owner, $criterion, 2);

        $this->actingAs($reviewer)
            ->from(route('upload.details', $datum))
            ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => '   '])
            ->assertSessionHasErrors('reason');

        $this->actingAs($reviewer)
            ->from(route('upload.details', $datum))
            ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('reason');

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(2.0, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'human_override_ai_rejected',
        ]);
    }

    public function test_unassigned_user_cannot_view_or_reject_gemini_accepted_resource(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $unassignedUser = User::factory()->create();
        $datum = $this->acceptedAiDatum($owner, $criterion, 2);

        $this->actingAs($unassignedUser)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee('Gemini tasdig‘ini bekor qilish');
        $this->actingAs($unassignedUser)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => 'Noto‘g‘ri.'])
            ->assertForbidden();

        $this->assertSame('accepted', $datum->fresh()->status);
    }

    public function test_super_admin_can_reject_gemini_accepted_resource(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $superAdmin = User::factory()->create([
            'hemis_id' => 9999999999,
            'rol' => ['super_admin'],
        ]);
        $datum = $this->acceptedAiDatum($owner, $criterion, 2);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Gemini tasdig‘ini bekor qilish');
        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), [
                'reason' => 'Super administrator tekshiruvida dalil noto‘g‘ri deb topildi.',
            ])
            ->assertRedirect(route('upload.details', $datum));

        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'human_override_ai_rejected',
        ]);
        $this->assertSame('cancelled', $datum->fresh()->status);
    }

    public function test_non_ai_or_non_gemini_acceptance_cannot_be_overridden(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $withoutAiHistory = Datum::query()->create([
            'name' => 'Inson tasdiqlagan resurs',
            'material' => ['type' => 'url', 'link' => 'https://example.com/manual-acceptance'],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 2,
        ]);
        $pending = $this->acceptedAiDatum($owner, $criterion, 0);
        $pending->update(['status' => 'checking']);

        foreach ([$withoutAiHistory, $pending] as $datum) {
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => 'Noto‘g‘ri.'])
                ->assertForbidden();
        }

        $this->assertSame('accepted', $withoutAiHistory->fresh()->status);
        $this->assertSame('checking', $pending->fresh()->status);
    }

    public function test_repeated_rejection_is_forbidden_and_does_not_duplicate_history(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->acceptedAiDatum($owner, $criterion, 2);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => 'Birinchi tekshiruv sababi.'])
            ->assertRedirect(route('upload.details', $datum));
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $datum), ['reason' => 'Takroriy urinish.'])
            ->assertForbidden();

        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'human_override_ai_rejected')
            ->count());
    }

    /** @return array{User, User, Report, Criterion} */
    private function context(): array
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        AiHumanReviewAssignment::query()->create([
            'hemis_id' => $reviewer->hemis_id,
            'active_slot' => 1,
            'assigned_at' => now(),
        ]);
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'AI inson nazorati'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '1.4',
            'name' => ['uz' => 'AI kriteriya'],
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
            'score' => 5,
        ]);

        return [$reviewer, $owner, $report, $criterion];
    }

    private function acceptedAiDatum(User $owner, Criterion $criterion, float $point): Datum
    {
        $datum = Datum::query()->create([
            'name' => 'Gemini tasdiqlagan tarjima.pdf',
            'material' => ['type' => 'url', 'link' => 'https://example.com/gemini-accepted/'.fake()->uuid()],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
            'author_count' => 2,
            'page_count' => 120,
            'reason' => 'Gemini mezon talablarini tasdiqladi.',
        ]);
        $datum->histories()->create([
            'user_id' => $owner->getKey(),
            'type' => 'success',
            'message' => 'Gemini mezon talablarini tasdiqladi.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }
}
