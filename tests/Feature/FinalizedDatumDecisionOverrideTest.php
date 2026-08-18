<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
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

    #[DataProvider('assignedReviewerModes')]
    public function test_assigned_reviewers_can_reverse_final_decisions_without_special_access(string $checking): void
    {
        [$reviewer, $owner, $criterion] = $this->context($checking);
        config()->set('kpi.super_admin_hemis_ids', []);
        config()->set('kpi.accepted_ai_reviewer_hemis_id', 9999999998);
        config()->set('kpi.assigned_final_decision_reviewer_hemis_id', 9999999997);

        if ($checking === 'ai') {
            config()->set('kpi.ai_human_review_criterion_reviewers', []);
            AiHumanReviewAssignment::query()->create([
                'hemis_id' => $reviewer->hemis_id,
                'active_slot' => 1,
                'assigned_at' => now(),
            ]);
        } else {
            CriterionReviewerAssignment::query()->create([
                'criterion_id' => $criterion->getKey(),
                'criterion_code' => $criterion->code,
                'hemis_id' => $reviewer->hemis_id,
            ]);
        }

        $accepted = $this->datum($owner, $criterion, 'accepted', 2, 'Tasdiqlangan.');
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0, 'Rad etilgan.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->get(route('upload.details', $cancelled))
            ->assertOk()
            ->assertSee('Rad etilgan resursni tasdiqlash');

        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $accepted), [
                'reason' => 'Mas\'ul qarorni qayta ko\'rib chiqdi.',
            ])
            ->assertRedirect(route('upload.details', $accepted));
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), ['point' => 3.5])
            ->assertRedirect(route('upload.details', $cancelled));

        $this->assertSame('cancelled', $accepted->fresh()->status);
        $this->assertSame('accepted', $cancelled->fresh()->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $accepted->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'human_override_rejected',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $cancelled->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'human_override_approved',
        ]);
    }

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
        $assignedCancelled = $this->datum($owner, $aiCriterion, 'cancelled', 0, 'AI rad etdi.');
        $manualAccepted = $this->datum($owner, $manualCriterion, 'accepted', 2, 'Mas’ul tasdiqladi.');
        $manualCancelled = $this->datum($owner, $manualCriterion, 'cancelled', 0, 'Mas’ul rad etdi.');
        $unassignedAccepted = $this->datum($owner, $unassignedCriterion, 'accepted', 2, 'AI tasdiqladi.');
        $unassignedCancelled = $this->datum($owner, $unassignedCriterion, 'cancelled', 0, 'AI rad etdi.');

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertSee($assigned->name)
            ->assertSee(route('upload.details', $assigned))
            ->assertDontSee($unassignedAccepted->name);
        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee($assignedCancelled->name)
            ->assertSee(route('upload.details', $assignedCancelled))
            ->assertDontSee($unassignedCancelled->name);
        $this->actingAs($reviewer)
            ->get(route('reviews.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertSee($manualAccepted->name)
            ->assertSee(route('upload.details', $manualAccepted))
            ->assertDontSee($assigned->name);
        $this->actingAs($reviewer)
            ->get(route('reviews.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee($manualCancelled->name)
            ->assertSee(route('upload.details', $manualCancelled))
            ->assertDontSee($assignedCancelled->name);

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

    /** @return array<string, array{string}> */
    public static function assignedReviewerModes(): array
    {
        return [
            'manual criterion reviewer' => ['manual'],
            'AI human reviewer' => ['ai'],
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
