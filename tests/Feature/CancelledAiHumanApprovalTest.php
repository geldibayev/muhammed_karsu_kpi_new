<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\DatumResourceIdentifier;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CancelledAiHumanApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_reviewer_sees_maximum_and_approves_ai_rejection_with_manual_point(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->cancelledAiDatum($owner, $criterion);
        DatumResourceIdentifier::query()->create([
            'datum_id' => $datum->getKey(),
            'report_id' => $report->getKey(),
            'user_id' => $owner->getKey(),
            'type' => 'canonical_url',
            'value_hash' => hash('sha256', 'canonical_url:https://example.com/rejected'),
            'active_value_hash' => null,
        ]);

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Rad etilgan resursni tasdiqlash')
            ->assertSee('0–5.0000')
            ->assertSee('max="5"', false)
            ->assertSee(route('ai-human-reviews.approve-cancelled', $datum));

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 4.25])
            ->assertRedirect(route('upload.details', $datum))
            ->assertSessionHasNoErrors();

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(4.25, $datum->point);
        $this->assertStringContainsString('4.2500', (string) $datum->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $reviewer->getKey(),
            'type' => 'success',
            'message_type' => 'human_override_ai_approved',
        ]);
        $this->assertDatabaseHas('criterion_points', [
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 4.25,
        ]);
        $this->assertDatabaseHas('points', [
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => 4.25,
        ]);
        $this->assertTrue($datum->resourceIdentifiers()->whereNotNull('active_value_hash')->exists());
    }

    public function test_point_is_required_numeric_non_negative_and_not_above_user_maximum(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->cancelledAiDatum($owner, $criterion);

        foreach ([
            [[], 'point'],
            [['point' => 'ball'], 'point'],
            [['point' => -0.01], 'point'],
            [['point' => 5.0001], 'point'],
        ] as [$payload, $errorKey]) {
            $this->actingAs($reviewer)
                ->from(route('upload.details', $datum))
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), $payload)
                ->assertSessionHasErrors($errorKey);
        }

        $this->assertSame('cancelled', $datum->fresh()->status);
        $this->assertSame(0.0, $datum->fresh()->point);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'human_override_ai_approved',
        ]);
    }

    public function test_exact_maximum_point_is_allowed(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->cancelledAiDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 5])
            ->assertRedirect(route('upload.details', $datum));

        $this->assertSame('accepted', $datum->fresh()->status);
        $this->assertSame(5.0, $datum->fresh()->point);
    }

    public function test_super_admin_can_approve_ai_rejection(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $superAdmin = User::factory()->create([
            'hemis_id' => 9999999999,
            'rol' => ['super_admin'],
        ]);
        $datum = $this->cancelledAiDatum($owner, $criterion);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Rad etilgan resursni tasdiqlash');
        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 3])
            ->assertRedirect(route('upload.details', $datum));

        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'human_override_ai_approved',
        ]);
    }

    public function test_unassigned_user_cannot_see_or_directly_use_approval(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $otherUser = User::factory()->create();
        $datum = $this->cancelledAiDatum($owner, $criterion);

        $this->actingAs($otherUser)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee('Rad etilgan resursni tasdiqlash');
        $this->actingAs($otherUser)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 3])
            ->assertForbidden();

        $this->assertSame('cancelled', $datum->fresh()->status);
    }

    public function test_cancelled_resources_without_latest_ai_rejection_can_also_be_approved(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $withoutAiHistory = Datum::query()->create([
            'name' => 'Inson rad etgan resurs',
            'material' => ['type' => 'url', 'link' => 'https://example.com/human-rejected'],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'cancelled',
            'point' => 0,
        ]);
        $humanRejectedAfterAi = $this->cancelledAiDatum($owner, $criterion);
        $humanRejectedAfterAi->histories()->create([
            'user_id' => $reviewer->getKey(),
            'type' => 'error',
            'message' => 'Inson rad etdi.',
            'message_type' => 'human_override_ai_rejected',
        ]);

        foreach ([$withoutAiHistory, $humanRejectedAfterAi] as $datum) {
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 2])
                ->assertRedirect(route('upload.details', $datum));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'human_override_approved',
            ]);
        }
    }

    public function test_historyless_legacy_resource_can_be_approved_when_another_cancelled_copy_exists(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $url = 'https://example.com/legacy-returned-resource';
        $otherCancelled = Datum::query()->create([
            'name' => 'Boshqa qaytarilgan nusxa',
            'material' => ['type' => 'url', 'link' => $url],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'cancelled',
        ]);
        DatumResourceIdentifier::query()->create([
            'datum_id' => $otherCancelled->getKey(),
            'report_id' => $report->getKey(),
            'user_id' => $owner->getKey(),
            'type' => 'canonical_url',
            'value_hash' => hash('sha256', 'canonical_url:'.$url),
            'active_value_hash' => null,
        ]);
        $legacyDatum = Datum::query()->create([
            'name' => 'Tarixsiz eski resurs',
            'material' => ['type' => 'url', 'link' => $url],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'cancelled',
        ]);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $legacyDatum), ['point' => 2])
            ->assertRedirect(route('upload.details', $legacyDatum))
            ->assertSessionHasNoErrors();

        $this->assertSame('accepted', $legacyDatum->fresh()->status);
        $this->assertTrue($legacyDatum->resourceIdentifiers()->whereNotNull('active_value_hash')->exists());
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $legacyDatum->getKey(),
            'message_type' => 'human_override_approved',
        ]);
    }

    public function test_repeated_approval_is_forbidden_and_history_is_not_duplicated(): void
    {
        [$reviewer, $owner, $report, $criterion] = $this->context();
        $datum = $this->cancelledAiDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 2])
            ->assertRedirect(route('upload.details', $datum));
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 3])
            ->assertForbidden();

        $this->assertSame(1, $datum->histories()
            ->where('message_type', 'human_override_ai_approved')
            ->count());
        $this->assertSame(2.0, $datum->fresh()->point);
    }

    /** @return array{User, User, Report, Criterion} */
    private function context(): array
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
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
            'name' => ['uz' => 'AI rad javobini tekshirish'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => '2.1.6',
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

    private function cancelledAiDatum(User $owner, Criterion $criterion): Datum
    {
        $datum = Datum::query()->create([
            'name' => 'Gemini rad etgan resurs.pdf',
            'material' => ['type' => 'url', 'link' => 'https://example.com/rejected/'.fake()->uuid()],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'cancelled',
            'point' => 0,
            'reason' => 'Gemini mezon talabiga mos emas deb topdi.',
        ]);
        $datum->histories()->create([
            'user_id' => $owner->getKey(),
            'type' => 'error',
            'message' => 'Gemini mezon talabiga mos emas deb topdi.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }
}
