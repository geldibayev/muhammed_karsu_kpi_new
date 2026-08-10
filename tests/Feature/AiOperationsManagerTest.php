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
use Tests\TestCase;

class AiOperationsManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_manager_can_evaluate_an_unassigned_pending_ai_resource(): void
    {
        [$manager, $owner, $criterion] = $this->context();
        $datum = $this->datum($owner, $criterion, 'checking', 0);

        $this->actingAs($manager)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Kvartil bilan tasdiqlash');

        $this->actingAs($manager)
            ->patch(route('reviews.approve', $datum), ['publication_tier' => 'q1'])
            ->assertRedirect(route('upload.details', $datum));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(20.0, $datum->point);
        $this->assertSame('q1', $datum->publication_tier);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $manager->getKey(),
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_configured_manager_can_correct_an_accepted_score_with_server_rules(): void
    {
        [$manager, $owner, $criterion] = $this->context();
        $datum = $this->datum($owner, $criterion, 'accepted', 99);

        $this->actingAs($manager)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Ballni to‘g‘rilash');

        $this->actingAs($manager)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('qayta hisoblanadi')
            ->assertDontSee('data-target="#reject-modal"', false);

        $this->actingAs($manager)
            ->patch(route('reviews.approve', $datum), [
                'publication_tier' => 'q2',
                'point' => 1,
            ])
            ->assertSessionHasErrors('point');

        $this->actingAs($manager)
            ->patch(route('reviews.approve', $datum), ['publication_tier' => 'q2'])
            ->assertRedirect(route('upload.details', $datum));

        $datum->refresh();
        $this->assertSame('accepted', $datum->status);
        $this->assertSame(15.0, $datum->point);
        $this->assertSame('q2', $datum->publication_tier);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $manager->getKey(),
            'message_type' => 'accepted_score_corrected',
        ]);
    }

    public function test_scopus_detail_overrides_use_publication_tier_and_server_point(): void
    {
        [, $owner, $criterion] = $this->context();
        $superAdmin = User::factory()->superAdmin()->create();
        $accepted = $this->datum($owner, $criterion, 'accepted', 20);
        $accepted->update(['publication_tier' => 'q1']);
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('name="publication_tier"', false)
            ->assertSee('Q2 — 15 ball')
            ->assertDontSee('name="point"', false);

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'score_change_reason' => 'Kvartil qayta tekshirildi.',
            ])
            ->assertSessionHasErrors('publication_tier');

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'publication_tier' => 'q5',
                'score_change_reason' => 'Kvartil qayta tekshirildi.',
            ])
            ->assertSessionHasErrors('publication_tier');

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'publication_tier' => 'q2',
                'point' => 1,
                'score_change_reason' => 'Kvartil qayta tekshirildi.',
            ])
            ->assertSessionHasErrors('point');

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $accepted), [
                'publication_tier' => 'q2',
                'score_change_reason' => 'Kvartil qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $accepted))
            ->assertSessionHasNoErrors();

        $this->assertSame(15.0, $accepted->fresh()->point);
        $this->assertSame('q2', $accepted->fresh()->publication_tier);

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $cancelled))
            ->assertOk()
            ->assertSee('name="publication_tier"', false)
            ->assertSee('Q3 — 10 ball')
            ->assertDontSee('name="point"', false);

        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled))
            ->assertSessionHasErrors('publication_tier');

        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), [
                'publication_tier' => 'q3',
                'point' => 1,
            ])
            ->assertSessionHasErrors('point');

        $this->actingAs($superAdmin)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), [
                'publication_tier' => 'q3',
            ])
            ->assertRedirect(route('upload.details', $cancelled))
            ->assertSessionHasNoErrors();

        $cancelled->refresh();
        $this->assertSame('accepted', $cancelled->status);
        $this->assertSame(10.0, $cancelled->point);
        $this->assertSame('q3', $cancelled->publication_tier);
    }

    public function test_another_user_cannot_evaluate_or_correct_ai_resources(): void
    {
        [, $owner, $criterion] = $this->context();
        $otherUser = User::factory()->create(['hemis_id' => '9999999999']);
        $pendingDatum = $this->datum($owner, $criterion, 'received', 0);
        $acceptedDatum = $this->datum($owner, $criterion, 'accepted', 5);

        $this->actingAs($otherUser)
            ->get(route('reviews.show', $pendingDatum))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->patch(route('reviews.approve', $acceptedDatum), ['publication_tier' => 'q1'])
            ->assertForbidden();

        $this->assertSame(5.0, $acceptedDatum->fresh()->point);
    }

    /** @return array{User, User, Criterion} */
    private function context(): array
    {
        config()->set('kpi.super_admin_hemis_ids', []);
        $manager = User::factory()->create([
            'hemis_id' => '3172011004',
            'rol' => ['teacher'],
        ]);
        config()->set('kpi.ai_operations_manager_hemis_id', $manager->hemis_id);
        config()->set('kpi.ai_status_viewer_hemis_id', $manager->hemis_id);

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
            'name' => ['uz' => 'AI boshqaruvi'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => Criterion::PUBLICATION_TIER_AI_HUMAN_REVIEW_CODE,
            'name' => ['uz' => 'Scopus maqolasi'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'ai',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 20,
        ]);

        return [$manager, $owner, $criterion];
    }

    private function datum(User $owner, Criterion $criterion, string $status, float $point): Datum
    {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => ['type' => 'url', 'link' => 'https://example.com/'.fake()->uuid()],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
        ]);
    }
}
