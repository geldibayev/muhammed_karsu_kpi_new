<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FinalDatumReviewConfirmationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_reviewer_confirms_final_review_with_name_time_and_star(): void
    {
        [$reviewer, $owner, $criterion] = $this->context();
        $datum = $this->acceptedDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Yakuniy tekshiruvni tasdiqlash')
            ->assertDontSee('Yakuniy tasdiq');

        $this->actingAs($reviewer)
            ->patch(route('submissions.final-confirmation.update', $datum))
            ->assertRedirect(route('upload.details', $datum));

        $history = DatumHistory::query()
            ->whereBelongsTo($datum)
            ->where('message_type', DatumHistory::FINAL_REVIEW_CONFIRMED)
            ->sole();

        $this->assertSame($reviewer->getKey(), $history->user_id);
        $this->assertNotNull($history->created_at);
        $this->assertStringContainsString('BERDIYEV BEKZOD', $history->message);

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Yakuniy tasdiq')
            ->assertSee('BERDIYEV BEKZOD')
            ->assertSee($history->created_at->format('d.m.Y H:i:s'))
            ->assertDontSee('Yakuniy tekshiruvni tasdiqlash');

        $this->actingAs($reviewer)
            ->patch(route('submissions.final-confirmation.update', $datum))
            ->assertForbidden();

        $this->assertSame(1, DatumHistory::query()
            ->whereBelongsTo($datum)
            ->where('message_type', DatumHistory::FINAL_REVIEW_CONFIRMED)
            ->count());
    }

    public function test_unassigned_users_and_cancelled_resources_cannot_be_finally_confirmed(): void
    {
        [$reviewer, $owner, $criterion] = $this->context();
        $unassignedReviewer = User::factory()->create();
        $accepted = $this->acceptedDatum($owner, $criterion);
        $cancelled = $this->acceptedDatum($owner, $criterion);
        $cancelled->update(['status' => 'cancelled', 'point' => 0]);

        foreach ([$owner, $unassignedReviewer] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->patch(route('submissions.final-confirmation.update', $accepted))
                ->assertForbidden();
        }

        $this->actingAs($reviewer)
            ->patch(route('submissions.final-confirmation.update', $cancelled))
            ->assertForbidden();

        $this->assertDatabaseMissing('datum_histories', [
            'message_type' => DatumHistory::FINAL_REVIEW_CONFIRMED,
        ]);
    }

    public function test_later_change_invalidates_final_confirmation(): void
    {
        [$reviewer, $owner, $criterion] = $this->context();
        $datum = $this->acceptedDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('submissions.final-confirmation.update', $datum))
            ->assertRedirect();

        $datum->histories()->create([
            'user_id' => $reviewer->getKey(),
            'type' => 'warning',
            'message' => 'Ball yakuniy tasdiqdan keyin o‘zgartirildi.',
            'message_type' => 'accepted_score_updated_by_reviewer',
        ]);

        $this->assertFalse($datum->fresh()->isFinalReviewConfirmed());

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee('Yakuniy tasdiq')
            ->assertSee('Yakuniy tekshiruvni tasdiqlash');
    }

    /** @return array{User, User, Criterion} */
    private function context(): array
    {
        $reviewer = User::factory()->create([
            'name' => [
                'full' => 'BERDIYEV BEKZOD',
                'short' => 'BERDIYEV B.',
            ],
            'rol' => ['teacher'],
        ]);
        $owner = User::factory()->create(['rol' => ['teacher']]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Yakuniy tekshiruv'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => 'test.final.confirmation',
            'name' => ['uz' => 'Yakuniy tekshiruv kriteriyasi'],
            'report_id' => $report->getKey(),
            'checking' => 'manual',
            'status' => '1',
        ]);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'criterion_code' => $criterion->code,
            'hemis_id' => $reviewer->hemis_id,
        ]);

        return [$reviewer, $owner, $criterion];
    }

    private function acceptedDatum(User $owner, Criterion $criterion): Datum
    {
        $datum = Datum::query()->create([
            'name' => 'Tasdiqlangan resurs',
            'material' => [
                'type' => 'url',
                'link' => 'https://example.com/'.fake()->uuid(),
            ],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 1,
            'reason' => 'Tasdiqlangan.',
        ]);
        $datum->histories()->create([
            'user_id' => $owner->getKey(),
            'type' => 'success',
            'message' => 'Resurs tasdiqlandi.',
            'message_type' => 'manual_review_approved',
        ]);

        return $datum;
    }
}
