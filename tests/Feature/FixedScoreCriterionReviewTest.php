<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FixedScoreCriterionReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_assigns_reviewer_and_all_pending_resources_become_visible(): void
    {
        $fixture = $this->createFixture();
        $otherOwner = User::factory()->create();
        $finishedDatum = Datum::query()->create([
            'name' => 'Yakunlangan resurs',
            'user_id' => $otherOwner->getKey(),
            'criterion_id' => $fixture['criterion']->getKey(),
            'status' => 'cancelled',
        ]);
        $otherDatum = Datum::query()->create([
            'name' => 'Boshqa mezon resursi',
            'user_id' => $otherOwner->getKey(),
            'criterion_id' => $fixture['target']->getKey(),
            'status' => 'received',
        ]);

        $this->artisan('kpi:reviewers:assign-fixed-score', [
            'criterion-code' => '2.1.4',
            'hemis-id' => (string) $fixture['reviewer']->hemis_id,
            'point' => '4',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('criterion_reviewer_assignments', [
            'criterion_id' => $fixture['criterion']->getKey(),
        ]);

        $this->runAssignmentCommand($fixture['reviewer']);
        $this->runAssignmentCommand($fixture['reviewer']);

        $this->assertDatabaseHas('criterion_reviewer_assignments', [
            'criterion_id' => $fixture['criterion']->getKey(),
            'hemis_id' => $fixture['reviewer']->hemis_id,
            'criterion_code' => '2.1.4',
        ]);
        $this->assertDatabaseCount('criterion_reviewer_assignments', 1);
        $this->assertDatabaseHas('criterion_manual_score_options', [
            'criterion_id' => $fixture['criterion']->getKey(),
            'code' => CriterionManualScoreOption::FIXED_APPROVAL_CODE,
            'point' => 4,
            'active' => true,
        ]);
        $this->assertDatabaseHas('criterion_manual_score_options', [
            'criterion_id' => $fixture['criterion']->getKey(),
            'code' => 'rector_order',
            'point' => 1,
            'active' => false,
        ]);

        $this->actingAs($fixture['reviewer'])
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertSee($fixture['datum']->name)
            ->assertDontSee($finishedDatum->name)
            ->assertDontSee($otherDatum->name);
    }

    public function test_fixed_score_reviewer_can_only_approve_or_reject_and_approval_awards_four_points(): void
    {
        $fixture = $this->createFixture();
        $unauthorizedUser = User::factory()->create();
        $this->runAssignmentCommand($fixture['reviewer']);

        $this->actingAs($unauthorizedUser)
            ->get(route('reviews.show', $fixture['datum']))
            ->assertForbidden();
        $this->actingAs($unauthorizedUser)
            ->patch(route('reviews.approve', $fixture['datum']))
            ->assertForbidden();

        $this->actingAs($fixture['reviewer'])
            ->get(route('reviews.show', $fixture['datum']))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertSee('4.00 ball')
            ->assertSee('Rad etish')
            ->assertDontSee('name="score_option_id"', false)
            ->assertDontSee('Boshqa kriteriyaga o‘tkazish');

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.transfer-criterion', $fixture['datum']), [
                'criterion_id' => $fixture['target']->getKey(),
            ])
            ->assertForbidden();

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.approve', $fixture['datum']))
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $fixture['datum']->getKey(),
            'status' => 'accepted',
            'point' => 4,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $fixture['datum']->getKey(),
            'user_id' => $fixture['reviewer']->getKey(),
            'message_type' => 'manual_review_approved',
        ]);

        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.approve', $fixture['datum']))
            ->assertForbidden();
    }

    public function test_fixed_score_rejection_requires_and_records_reason(): void
    {
        $fixture = $this->createFixture();
        $this->runAssignmentCommand($fixture['reviewer']);

        $this->actingAs($fixture['reviewer'])
            ->from(route('reviews.show', $fixture['datum']))
            ->patch(route('reviews.reject', $fixture['datum']), ['reason' => ''])
            ->assertRedirect(route('reviews.show', $fixture['datum']))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('data', [
            'id' => $fixture['datum']->getKey(),
            'status' => 'received',
        ]);

        $reason = 'Resurs xalqaro loyiha mezoni talabini tasdiqlamaydi.';
        $this->actingAs($fixture['reviewer'])
            ->patch(route('reviews.reject', $fixture['datum']), ['reason' => $reason])
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $fixture['datum']->getKey(),
            'status' => 'cancelled',
            'point' => 0,
            'reason' => $reason,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $fixture['datum']->getKey(),
            'user_id' => $fixture['reviewer']->getKey(),
            'message' => $reason,
            'message_type' => 'manual_review_rejected',
        ]);
    }

    /**
     * @return array{
     *     reviewer: User,
     *     owner: User,
     *     criterion: Criterion,
     *     target: Criterion,
     *     datum: Datum
     * }
     */
    private function createFixture(): array
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'KPI hisoboti'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'name' => ['uz' => 'Raqobat reyting tizimida'],
            'status' => '1',
        ]);
        Criterion::query()->create([
            'id' => 1,
            'name' => ['uz' => 'Birinchi bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
        ]);
        $secondSection = Criterion::query()->create([
            'id' => 12,
            'name' => ['uz' => 'Ikkinchi bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
        ]);
        $criterion = Criterion::query()->create([
            'id' => 16,
            'code' => '2.1.4',
            'name' => ['uz' => 'Xalqaro loyihalarda ishtiroki'],
            'parent_id' => $secondSection->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'res_type' => 'all',
            'upload' => '1',
            'status' => '1',
        ]);
        $target = Criterion::query()->create([
            'id' => 17,
            'code' => '2.1.5',
            'name' => ['uz' => 'Boshqa mezon'],
            'parent_id' => $secondSection->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'res_type' => 'all',
            'upload' => '1',
            'status' => '1',
        ]);

        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 4,
        ]);
        CriterionManualScoreOption::query()->create([
            'criterion_id' => $criterion->getKey(),
            'code' => 'rector_order',
            'label' => ['uz' => 'Rektor buyrug‘i bilan tasdiqlangan loyiha'],
            'point' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);

        $reviewer = User::factory()->create(['hemis_id' => 3462611061]);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $datum = Datum::query()->create([
            'name' => 'Xalqaro loyiha hujjati',
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'received',
        ]);

        return compact('reviewer', 'owner', 'criterion', 'target', 'datum');
    }

    private function runAssignmentCommand(User $reviewer): void
    {
        $this->artisan('kpi:reviewers:assign-fixed-score', [
            'criterion-code' => '2.1.4',
            'hemis-id' => (string) $reviewer->hemis_id,
            'point' => '4',
        ])->assertSuccessful();
    }
}
