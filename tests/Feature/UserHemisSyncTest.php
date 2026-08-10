<?php

namespace Tests\Feature;

use App\Actions\GetRatingIndexData;
use App\Actions\SyncHemisWorkplacesForLogin;
use App\Enums\RatingMode;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Tests\TestCase;
use UnexpectedValueException;

class UserHemisSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_super_admin_can_see_and_use_the_hemis_sync_action(): void
    {
        $teacher = User::factory()->create();
        $target = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $allUsersRatingUrl = route('ratings.index', ['mode' => 'all_users']);
        $this->mock(
            GetRatingIndexData::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('handle')
                ->twice()
                ->andReturn([
                    'departments' => collect(),
                    'faculties' => collect(),
                    'positions' => collect(),
                    'filters' => ['mode' => 'all_users'],
                    'mode' => RatingMode::AllUsers,
                    'report' => null,
                    'unitRankings' => null,
                    'users' => new LengthAwarePaginator([$target], 1, 25),
                ]),
        );

        $this->actingAs($teacher)
            ->get($allUsersRatingUrl)
            ->assertOk()
            ->assertDontSee('HEMISdan yangilash');
        $this->actingAs($teacher)
            ->post(route('users.hemis-sync.store', $target))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get($allUsersRatingUrl)
            ->assertOk()
            ->assertSee('HEMISdan yangilash')
            ->assertSee(route('users.hemis-sync.store', $target));
    }

    public function test_super_admin_can_sync_a_user_and_receives_a_success_notification(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->mock(
            SyncHemisWorkplacesForLogin::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('handle')
                ->once()
                ->withArgs(fn (User $user): bool => $user->is($target))
                ->andReturn($target),
        );

        $this->actingAs($superAdmin)
            ->from(route('ratings.index'))
            ->post(route('users.hemis-sync.store', $target))
            ->assertRedirect(route('ratings.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'HEMIS javobi olindi'));
    }

    public function test_hemis_failure_is_shown_as_an_error_notification(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->mock(
            SyncHemisWorkplacesForLogin::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('handle')
                ->once()
                ->andThrow(new UnexpectedValueException('Yaroqsiz HEMIS javobi')),
        );

        $this->actingAs($superAdmin)
            ->from(route('ratings.index'))
            ->post(route('users.hemis-sync.store', $target))
            ->assertRedirect(route('ratings.index'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'yangilab bo‘lmadi'));
    }
}
