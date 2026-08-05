<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionReviewerAssignment;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CriterionRatingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_and_unknown_role_cannot_view_criterion_rating(): void
    {
        $criterion = $this->createCriterion();

        $this->get(route('criteria.ratings.show', $criterion))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->withRole('unknown')->create())
            ->get(route('criteria.ratings.show', $criterion))
            ->assertForbidden();
    }

    public function test_users_are_ranked_together_by_criterion_point_without_degree_groups(): void
    {
        $viewer = User::factory()->create();
        $withDegree = User::factory()->create([
            'name' => $this->userName('Darajali Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $withoutDegree = User::factory()->create([
            'name' => $this->userName('Darajasiz Ustoz'),
            'degree' => 'no_degrees',
        ]);
        $criterion = $this->createCriterion('Kriteriya reytingi');

        $this->createPoint($withDegree, $criterion, 5.5);
        $this->createPoint($withoutDegree, $criterion, 9);

        $response = $this->actingAs($viewer)
            ->get(route('criteria.ratings.show', $criterion));

        $response
            ->assertOk()
            ->assertSee('Kriteriya reytingi')
            ->assertSee('Darajali Ustoz')
            ->assertSee('Darajasiz Ustoz')
            ->assertSee(route('ratings.show', $withDegree))
            ->assertSee(route('ratings.show', $withoutDegree))
            ->assertSeeInOrder(['Darajasiz Ustoz', 'Darajali Ustoz'])
            ->assertViewHas('rankedPoints', function (LengthAwarePaginator $points) use ($withoutDegree, $withDegree): bool {
                return $points->total() === 2
                    && $points->items()[0]->user->is($withoutDegree)
                    && (float) $points->items()[0]->point === 9.0
                    && $points->items()[1]->user->is($withDegree)
                    && (float) $points->items()[1]->point === 5.5;
            });
    }

    public function test_home_shows_rating_action_ai_and_assigned_reviewer_name(): void
    {
        $viewer = User::factory()->create();
        $reviewer = User::factory()->create([
            'name' => $this->userName('Masul Tekshiruvchi'),
        ]);
        $manualCriterion = $this->createCriterion('Manual mezon');
        $aiCriterion = $this->createSiblingCriterion($manualCriterion, 'AI mezon', 'ai');

        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $manualCriterion->id,
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => '1/'.$manualCriterion->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Masul Tekshiruvchi')
            ->assertSee('Sunʼiy intellekt')
            ->assertSee(route('criteria.ratings.show', $manualCriterion))
            ->assertSee(route('criteria.ratings.show', $aiCriterion));
    }

    private function createCriterion(string $name = 'Test kriteriyasi'): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol hisobot'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Asosiy bo‘lim'],
            'report_id' => $report->id,
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => $name],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createSiblingCriterion(
        Criterion $criterion,
        string $name,
        string $checking,
    ): Criterion {
        return Criterion::query()->create([
            'name' => ['uz' => $name],
            'parent_id' => $criterion->parent_id,
            'report_id' => $criterion->report_id,
            'checking' => $checking,
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createPoint(User $user, Criterion $criterion, float $point): Point
    {
        return Point::query()->create([
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'report_id' => $criterion->report_id,
            'point' => $point,
        ]);
    }

    /** @return array<string, string> */
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
