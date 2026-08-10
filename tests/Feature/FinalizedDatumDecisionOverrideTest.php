<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinalizedDatumDecisionOverrideTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_reviewer_manages_final_decisions_only_for_assigned_criteria(): void
    {
        [$reviewer, $owner, $aiCriterion] = $this->context('ai');
        $reviewer->update(['hemis_id' => 3462011207]);
        config()->set('kpi.accepted_ai_reviewer_hemis_id', $reviewer->hemis_id);
        config()->set('kpi.assigned_final_decision_reviewer_hemis_id', $reviewer->hemis_id);
        config()->set('kpi.ai_human_review_criterion_reviewers', [
            $aiCriterion->code => $reviewer->hemis_id,
        ]);

        $manualCriterion = $aiCriterion->replicate();
        $manualCriterion->fill([
            'code' => 'test.manual.assigned',
            'checking' => 'manual',
        ])->save();
        CriterionEvaluation::query()->create([
            'criterion_id' => $manualCriterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 5,
        ]);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $manualCriterion->getKey(),
            'criterion_code' => $manualCriterion->code,
            'hemis_id' => $reviewer->hemis_id,
        ]);

        $unassignedCriterion = $aiCriterion->replicate();
        $unassignedCriterion->fill(['code' => 'test.ai.unassigned'])->save();
        CriterionEvaluation::query()->create([
            'criterion_id' => $unassignedCriterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 5,
        ]);

        $assigned = $this->datum($owner, $aiCriterion, 'accepted', 2, 'AI tasdiqladi.');
        $manualAccepted = $this->datum($owner, $manualCriterion, 'accepted', 2, 'Mas’ul tasdiqladi.');
        $manualCancelled = $this->datum($owner, $manualCriterion, 'cancelled', 0, 'Mas’ul rad etdi.');
        $unassignedAccepted = $this->datum($owner, $unassignedCriterion, 'accepted', 2, 'AI tasdiqladi.');
        $unassignedCancelled = $this->datum($owner, $unassignedCriterion, 'cancelled', 0, 'AI rad etdi.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $assigned))
            ->assertOk()
            ->assertSee('Ballni o‘zgartirish')
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->get(route('upload.details', $manualAccepted))
            ->assertOk()
            ->assertSee('Ballni o‘zgartirish')
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->get(route('upload.details', $manualCancelled))
            ->assertOk()
            ->assertSee('Rad etilgan resursni tasdiqlash');

        $this->actingAs($reviewer)
            ->patch(route('submissions.accepted-score.update', $assigned), [
                'point' => 4,
                'score_change_reason' => 'Ball qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $assigned));
        $this->assertSame(4.0, $assigned->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $assigned->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'accepted_score_updated_by_reviewer',
        ]);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $assigned), [
                'reason' => 'Tasdiqlash xato bo‘lgan.',
            ])
            ->assertRedirect(route('upload.details', $assigned));
        $this->assertSame('cancelled', $assigned->fresh()->status);

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $assigned), ['point' => 3.5])
            ->assertRedirect(route('upload.details', $assigned));
        $this->assertSame('accepted', $assigned->fresh()->status);
        $this->assertSame(3.5, $assigned->fresh()->point);

        $this->actingAs($reviewer)
            ->get(route('upload.details', $unassignedAccepted))
            ->assertOk()
            ->assertDontSee('Ballni o‘zgartirish')
            ->assertDontSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->patch(route('submissions.accepted-score.update', $unassignedAccepted), [
                'point' => 1,
                'score_change_reason' => 'Ruxsatsiz urinish.',
            ])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $unassignedAccepted), [
                'reason' => 'Ruxsatsiz urinish.',
            ])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $unassignedCancelled), ['point' => 1])
            ->assertForbidden();
    }

    public function test_legacy_ai_decisions_without_ai_history_can_be_reversed_in_both_directions(): void
    {
        [$reviewer, $owner, $criterion] = $this->context('ai');
        $accepted = $this->datum($owner, $criterion, 'accepted', 3, 'Oldingi tasdiq.');
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0, 'Oldingi rad javobi.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $accepted), [
                'reason' => 'Eski qaror qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $accepted));

        $this->assertSame('cancelled', $accepted->fresh()->status);
        $this->assertSame(0.0, $accepted->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $accepted->getKey(),
            'message_type' => 'human_override_rejected',
        ]);

        foreach ([$accepted, $cancelled] as $datum) {
            $this->actingAs($reviewer)
                ->get(route('upload.details', $datum))
                ->assertOk()
                ->assertSee('Rad etilgan resursni tasdiqlash')
                ->assertSee('0–5.0000');
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 4.25])
                ->assertRedirect(route('upload.details', $datum));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame(4.25, $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'human_override_approved',
            ]);
        }
    }

    #[DataProvider('checkingModes')]
    public function test_final_decisions_can_be_reversed_for_every_checking_mode(string $checking): void
    {
        [$reviewer, $owner, $criterion] = $this->context($checking);
        $accepted = $this->datum($owner, $criterion, 'accepted', 3, 'Manual tasdiq.');
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0, 'Manual rad javobi.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->get(route('upload.details', $cancelled))
            ->assertOk()
            ->assertSee('Rad etilgan resursni tasdiqlash')
            ->assertSee('max="5"', false);
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $accepted), [
                'reason' => 'Yakuniy qaror qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $accepted));

        $this->assertSame('cancelled', $accepted->fresh()->status);
        $this->assertSame(0.0, $accepted->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $accepted->getKey(),
            'message_type' => 'human_override_rejected',
        ]);

        foreach ([$accepted, $cancelled] as $datum) {
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 4.25])
                ->assertRedirect(route('upload.details', $datum));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame(4.25, $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'human_override_approved',
            ]);
        }
    }

    /** @return array<string, array{string}> */
    public static function checkingModes(): array
    {
        return [
            'manual' => ['manual'],
            'pointing' => ['pointing'],
            'department' => ['department'],
            'HEMIS' => ['hemis:employee'],
            'site' => ['site:publication'],
        ];
    }

    /** @return array{User, User, Criterion} */
    private function context(string $checking): array
    {
        $reviewer = User::factory()->create([
            'hemis_id' => 3172011004,
            'rol' => ['teacher'],
        ]);
        config()->set('kpi.accepted_ai_reviewer_hemis_id', $reviewer->hemis_id);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'Qarorlarni tekshirish'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => fake()->unique()->numerify('#.#.#'),
            'name' => ['uz' => 'Kriteriya'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => $checking,
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 5,
        ]);

        return [$reviewer, $owner, $criterion];
    }

    private function datum(
        User $owner,
        Criterion $criterion,
        string $status,
        float $point,
        string $reason,
    ): Datum {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => [
                'type' => 'url',
                'link' => 'https://example.com/'.fake()->uuid(),
            ],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'reason' => $reason,
        ]);
    }
}
