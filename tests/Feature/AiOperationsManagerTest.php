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
            ->assertRedirect(route('ai-status.index'));

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
