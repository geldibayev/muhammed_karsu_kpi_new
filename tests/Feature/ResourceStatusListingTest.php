<?php

namespace Tests\Feature;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ResourceStatusListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_resource_status_listing(): void
    {
        $this->get(route('files.show', DatumStatus::Received))
            ->assertRedirect(route('login'));
    }

    public function test_each_status_lists_only_the_authenticated_users_matching_resources(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $criterion = $this->createCriterion();

        foreach (DatumStatus::cases() as $status) {
            $this->createDatum($owner, $criterion, $status, 'Owner '.$status->value);
            $this->createDatum($otherUser, $criterion, $status, 'Foreign '.$status->value);
        }

        foreach (DatumStatus::cases() as $status) {
            $response = $this->actingAs($owner)
                ->get(route('files.show', $status))
                ->assertOk()
                ->assertSee('Owner '.$status->value)
                ->assertSee($status->label())
                ->assertDontSee('Foreign '.$status->value);

            foreach (DatumStatus::cases() as $otherStatus) {
                if ($otherStatus !== $status) {
                    $response->assertDontSee('Owner '.$otherStatus->value);
                }
            }
        }
    }

    public function test_unknown_and_deleted_statuses_return_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home/files/unknown')->assertNotFound();
        $this->actingAs($user)->get('/home/files/deleted')->assertNotFound();
    }

    public function test_status_listing_is_paginated_with_bootstrap_markup(): void
    {
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();

        foreach (range(1, 21) as $number) {
            $this->createDatum(
                $owner,
                $criterion,
                DatumStatus::Received,
                'Paginated resource '.$number,
            );
        }

        $this->actingAs($owner)
            ->get(route('files.show', DatumStatus::Received))
            ->assertOk()
            ->assertViewHas('data', function (mixed $data): bool {
                return $data instanceof LengthAwarePaginator
                    && $data->count() === 20
                    && $data->total() === 21;
            })
            ->assertSee('class="pagination"', false);
    }

    public function test_owner_can_view_resource_details_but_another_user_cannot(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $datum = $this->createDatum(
            $owner,
            $this->createCriterion(),
            DatumStatus::Accepted,
            'Detailed resource',
            [
                'material' => [
                    'type' => 'url',
                    'link' => 'https://example.com/resource',
                    'article' => ['authors' => 'Test Author'],
                ],
                'point' => 8.5,
                'reason' => '<script>alert("x")</script>',
            ],
        );

        $this->actingAs($owner)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Detailed resource')
            ->assertSee('Test Author')
            ->assertSee('8.50')
            ->assertSee('https://example.com/resource', false)
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("x")</script>', false);

        $this->actingAs($otherUser)
            ->get(route('upload.details', $datum))
            ->assertForbidden();
    }

    public function test_unsafe_legacy_url_is_not_rendered_as_a_link(): void
    {
        $owner = User::factory()->create();
        $datum = $this->createDatum(
            $owner,
            $this->createCriterion(),
            DatumStatus::Received,
            'Unsafe legacy resource',
            [
                'material' => [
                    'type' => 'url',
                    'link' => 'javascript:alert(1)',
                ],
            ],
        );

        $this->actingAs($owner)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    private function createCriterion(): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Status hisoboti'],
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => 'Status mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createDatum(
        User $user,
        Criterion $criterion,
        DatumStatus $status,
        string $name,
        array $attributes = [],
    ): Datum {
        return Datum::query()->create(array_merge([
            'name' => $name,
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'uploads/'.$name.'.pdf',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => $status->value,
            'point' => 0,
        ], $attributes));
    }
}
