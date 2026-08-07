<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedUserSummaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_header_shows_profile_image_and_active_report_total_out_of_one_hundred(): void
    {
        $user = User::factory()->create([
            'image' => json_encode([
                'min' => 'https://hemis.example/profile.jpg',
                'max' => 'https://hemis.example/profile-large.jpg',
            ], JSON_THROW_ON_ERROR),
        ]);
        $activeReport = $this->report('Faol hisobot', '1');
        $oldReport = $this->report('Eski hisobot', '0');

        $this->point($user, $activeReport, 12.5);
        $this->point($user, $oldReport, 80);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('https://hemis.example/profile.jpg')
            ->assertSee('data-rating-avatar-image', false)
            ->assertSee('data-rating-avatar-fallback', false)
            ->assertSee('text-success', false)
            ->assertSee('12.50 / 100 ball')
            ->assertSee('Ilmiy unvon kiritilmagan')
            ->assertSeeInOrder(['12.50 / 100 ball', 'https://hemis.example/profile.jpg'])
            ->assertDontSee('92.50 / 100 ball');
    }

    public function test_header_shows_avatar_fallback_and_zero_when_there_is_no_active_report(): void
    {
        $user = User::factory()->create(['image' => null]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-rating-avatar-fallback', false)
            ->assertSee('0.00 / 100 ball');
    }

    private function report(string $name, string $status): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => $status,
        ]);
    }

    private function point(User $user, Report $report, float $point): void
    {
        $formula = Formula::query()->firstOrCreate(
            ['code' => Formula::Maximum],
            ['name' => ['uz' => 'Maksimal'], 'status' => '1'],
        );
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Mezon'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'status' => '1',
        ]);

        Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => $point,
        ]);
    }
}
