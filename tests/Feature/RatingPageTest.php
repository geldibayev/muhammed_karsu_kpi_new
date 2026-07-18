<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class RatingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_and_user_without_a_known_role_cannot_view_ratings(): void
    {
        $this->get(route('ratings.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->withRole('unknown')->create();

        $this->actingAs($user)
            ->get(route('ratings.index'))
            ->assertForbidden();
    }

    public function test_users_are_ranked_by_their_active_report_total(): void
    {
        $viewer = User::factory()->create();
        $firstUser = User::factory()->create([
            'name' => $this->userName('Birinchi Ustoz'),
        ]);
        $secondUser = User::factory()->create([
            'name' => $this->userName('Ikkinchi Ustoz'),
        ]);
        $zeroPointUser = User::factory()->create([
            'name' => $this->userName('<script>alert(1)</script>'),
        ]);

        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $firstCriterion = $this->createCriterion($activeReport, 'Birinchi mezon');
        $secondCriterion = $this->createCriterion($activeReport, 'Ikkinchi mezon');
        $oldCriterion = $this->createCriterion($oldReport, 'Eski mezon');

        $this->createPoint($firstUser, $firstCriterion, $activeReport, 7.5);
        $this->createPoint($firstUser, $secondCriterion, $activeReport, 4.5);
        $this->createPoint($secondUser, $firstCriterion, $activeReport, 5);
        $this->createPoint($secondUser, $oldCriterion, $oldReport, 100);

        $response = $this->actingAs($viewer)->get(route('ratings.index'));

        $response
            ->assertOk()
            ->assertSee('Reyting')
            ->assertSee('Faol hisobot')
            ->assertSee('12.00')
            ->assertSee('5.00')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSeeInOrder(['Birinchi Ustoz', 'Ikkinchi Ustoz'])
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($firstUser, $secondUser, $zeroPointUser): bool {
                return $users->total() === 4
                    && $users->items()[0]->is($firstUser)
                    && (float) $users->items()[0]->total_points === 12.0
                    && $users->items()[1]->is($secondUser)
                    && (float) $users->items()[1]->total_points === 5.0
                    && $users->getCollection()->contains(fn (User $user): bool => $user->is($zeroPointUser));
            });
    }

    public function test_ratings_page_handles_the_absence_of_an_active_report(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Faol hisobot topilmadi')
            ->assertViewHas('report', null);
    }

    /** @return array<string, string> */
    private function userName(string $fullName): array
    {
        return [
            'full' => $fullName,
            'first' => $fullName,
            'last' => '',
            'third' => '',
            'short' => $fullName,
        ];
    }

    private function createReport(string $name, string $status): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => $status,
        ]);
    }

    private function createCriterion(Report $report, string $name): Criterion
    {
        return Criterion::query()->create([
            'name' => ['uz' => $name],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
    }

    private function createPoint(User $user, Criterion $criterion, Report $report, float $point): Point
    {
        return Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => $point,
        ]);
    }
}
