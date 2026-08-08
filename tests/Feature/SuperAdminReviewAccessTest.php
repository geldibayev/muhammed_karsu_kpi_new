<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SuperAdminReviewAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_hemis_user_preserves_roles_and_becomes_super_admin(): void
    {
        config()->set('kpi.super_admin_hemis_ids', ['3172011004']);
        $user = User::factory()->create([
            'hemis_id' => 3172011004,
            'rol' => ['department'],
        ]);

        $user->ensureConfiguredSuperAdminRole();
        $user->save();

        $this->assertEqualsCanonicalizing(
            ['department', 'super_admin', 'teacher'],
            $user->fresh()->rol,
        );
        $this->assertTrue(Gate::forUser($user->fresh())->allows('manage-ai-operations'));
        $this->assertTrue(Gate::forUser($user->fresh())->allows('manage-kpi-settings'));
    }

    public function test_super_admin_can_see_and_review_every_pending_submission(): void
    {
        [$manualCriterion, $aiCriterion] = $this->criteria();
        $owner = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $ordinaryUser = User::factory()->create();
        $manualDatum = $this->datum($owner, $manualCriterion, 'received', 'Boshqa mas’ulning qo‘lda resursi');
        $aiDatum = $this->datum($owner, $aiCriterion, 'checking', 'Boshqa mas’ulning AI resursi');

        $this->actingAs($superAdmin)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertSee($manualDatum->name);
        $this->actingAs($superAdmin)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee($aiDatum->name);
        $this->actingAs($superAdmin)
            ->get(route('ai-human-reviews.index', ['criterion' => $aiCriterion->getKey()]))
            ->assertOk()
            ->assertSee($aiDatum->name)
            ->assertDontSee($manualDatum->name);
        $this->actingAs($superAdmin)->get(route('reviews.show', $manualDatum))->assertOk();
        $this->actingAs($superAdmin)->get(route('reviews.show', $aiDatum))->assertOk();

        $this->actingAs($ordinaryUser)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($ordinaryUser)->get(route('ai-human-reviews.index'))->assertForbidden();
        $this->actingAs($ordinaryUser)->get(route('reviews.show', $manualDatum))->assertForbidden();
    }

    public function test_super_admin_can_restore_cancelled_and_change_accepted_score_with_audit(): void
    {
        [$criterion] = $this->criteria();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $superAdmin = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 'Rad etilgan resurs');
        $accepted = $this->datum($owner, $criterion, 'accepted', 'Tasdiqlangan resurs', 2);

        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), ['point' => 3.5])
            ->assertRedirect(route('upload.details', $cancelled));

        $this->assertSame('accepted', $cancelled->fresh()->status);
        $this->assertSame(3.5, $cancelled->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $cancelled->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'human_override_approved',
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'point' => 4.25,
                'score_change_reason' => 'Dastlabki ball noto‘g‘ri hisoblangan.',
            ])
            ->assertRedirect(route('upload.details', $accepted));

        $this->assertSame(4.25, $accepted->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $accepted->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'accepted_score_updated_by_super_admin',
        ]);
        $this->assertStringContainsString('2.0000 dan 4.2500 ga', $accepted->fresh()->reason);

        $this->actingAs($superAdmin)
            ->from(route('upload.details', $accepted))
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'point' => 5.01,
                'score_change_reason' => 'Chegaradan oshirishga urinish.',
            ])
            ->assertRedirect(route('upload.details', $accepted))
            ->assertSessionHasErrors('point');

        $this->assertSame(4.25, $accepted->fresh()->point);

        $ordinaryUser = User::factory()->create();
        $this->actingAs($ordinaryUser)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'point' => 1,
                'score_change_reason' => 'Ruxsatsiz urinish.',
            ])
            ->assertForbidden();
    }

    /** @return array{Criterion, Criterion} */
    private function criteria(): array
    {
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
            'name' => ['uz' => 'Super admin testi'],
            'status' => '1',
        ]);

        $criteria = collect(['manual', 'ai'])->map(function (string $checking) use ($formula, $report): Criterion {
            $criterion = Criterion::query()->create([
                'code' => $checking === 'manual' ? 'test.manual' : 'test.ai',
                'name' => ['uz' => $checking.' mezon'],
                'report_id' => $report->getKey(),
                'formula_id' => $formula->getKey(),
                'checking' => $checking,
                'upload' => '1',
                'status' => '1',
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => 'no_degrees',
                'has' => '1',
                'score' => 5,
            ]);

            return $criterion;
        });

        return [$criteria->get(0), $criteria->get(1)];
    }

    private function datum(
        User $owner,
        Criterion $criterion,
        string $status,
        string $name,
        float $point = 0,
    ): Datum {
        return Datum::query()->create([
            'name' => $name,
            'material' => ['type' => 'url', 'link' => 'https://example.com/'.fake()->uuid()],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
