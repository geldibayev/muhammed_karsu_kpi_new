<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ResourceStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_viewer_sees_per_criterion_statistics_column_on_home(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $viewer = User::factory()->create(['hemis_id' => 3172011004]);
        $otherUser = User::factory()->create(['hemis_id' => 9999999999]);
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol statistika hisoboti'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Statistika bo‘limi'],
            'report_id' => $report->id,
            'upload' => '0',
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Resursli kriteriya'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ]);
        $emptyCriterion = Criterion::query()->create([
            'name' => ['uz' => 'Bo‘sh kriteriya'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ]);

        foreach ([
            'received' => 1,
            'checking' => 2,
            'accepted' => 3,
            'cancelled' => 4,
            'deleted' => 5,
        ] as $status => $count) {
            foreach (range(1, $count) as $number) {
                Datum::query()->create([
                    'name' => "{$status} resurs {$number}",
                    'user_id' => $number % 2 === 0 ? $firstOwner->id : $secondOwner->id,
                    'criterion_id' => $criterion->id,
                    'status' => $status,
                    'point' => 0,
                ]);
            }
        }

        $this->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Resurslar statistikasi')
            ->assertSee('Jami: 15')
            ->assertSee('Tekshirilgan: 3')
            ->assertSee('Tekshirilmagan: 3')
            ->assertSee('Qaytarilgan: 4')
            ->assertSee('O‘chirilgan: 5')
            ->assertViewHas('showsCriterionResourceStatistics', true)
            ->assertViewHas('criterionResourceStatistics', function ($statistics) use ($criterion, $emptyCriterion): bool {
                return $statistics->get($criterion->id) === [
                    'total' => 15,
                    'checked' => 3,
                    'unchecked' => 3,
                    'returned' => 4,
                    'deleted' => 5,
                    'other' => 0,
                ] && $statistics->get($emptyCriterion->id) === [
                    'total' => 0,
                    'checked' => 0,
                    'unchecked' => 0,
                    'returned' => 0,
                    'deleted' => 0,
                    'other' => 0,
                ];
            });

        $this->actingAs($otherUser)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Resurslar statistikasi')
            ->assertDontSee('Tekshirilgan: 3')
            ->assertViewHas('showsCriterionResourceStatistics', false)
            ->assertViewHas('criterionResourceStatistics', fn ($statistics): bool => $statistics->isEmpty());
    }

    public function test_configured_hemis_user_sees_all_resource_status_statistics(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $viewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();

        foreach ([
            'received' => 1,
            'checking' => 2,
            'accepted' => 3,
            'cancelled' => 4,
            'deleted' => 5,
        ] as $status => $count) {
            foreach (range(1, $count) as $number) {
                Datum::query()->create([
                    'name' => $status.' resurs '.$number,
                    'user_id' => $owner->id,
                    'criterion_id' => $criterion->id,
                    'status' => $status,
                    'point' => 0,
                ]);
            }
        }

        $this->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Statistika')
            ->assertSee(route('statistics.index'));

        $this->actingAs($viewer)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertSee('Jami resurslar')
            ->assertSee('Bugun yuklangan')
            ->assertSee('Joriy haftada yuklangan')
            ->assertSee('Yuborilgan')
            ->assertSee('Ko‘rib chiqilmoqda')
            ->assertSee('Tasdiqlangan')
            ->assertSee('Qaytarilgan')
            ->assertSee('O‘chirilgan')
            ->assertViewHas('statistics', function (array $statistics): bool {
                $counts = collect($statistics['statuses'])
                    ->pluck('count', 'value')
                    ->all();

                return $statistics['total'] === 15
                    && $statistics['uploads']['today'] === 15
                    && $statistics['uploads']['current_week'] === 15
                    && $counts === [
                        'received' => 1,
                        'checking' => 2,
                        'accepted' => 3,
                        'cancelled' => 4,
                        'deleted' => 5,
                    ];
            });
    }

    public function test_empty_statistics_are_returned_as_zeroes(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $viewer = User::factory()->create(['hemis_id' => 3172011004]);

        $this->actingAs($viewer)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertViewHas('statistics', function (array $statistics): bool {
                return $statistics['total'] === 0
                    && $statistics['uploads']['today'] === 0
                    && $statistics['uploads']['current_week'] === 0
                    && collect($statistics['statuses'])->every(
                        fn (array $status): bool => $status['count'] === 0
                            && $status['percentage'] === 0.0,
                    );
            });
    }

    public function test_daily_and_weekly_upload_statistics_use_their_exact_date_boundaries(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $this->travelTo(CarbonImmutable::parse('2026-08-05 12:00:00', 'Asia/Tashkent'));

        $viewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();

        $this->createDatumAt($owner, $criterion, '2026-08-05 08:30:00');
        $this->createDatumAt($owner, $criterion, '2026-08-03 00:00:00');
        $this->createDatumAt($owner, $criterion, '2026-08-02 23:59:59');
        $this->createDatumAt($owner, $criterion, '2026-08-06 00:00:00');

        $this->actingAs($viewer)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertViewHas('statistics', function (array $statistics): bool {
                return $statistics['uploads'] === [
                    'today' => 1,
                    'current_week' => 2,
                    'today_label' => '05.08.2026',
                    'current_week_label' => '03.08.2026 — 05.08.2026',
                ];
            });
    }

    public function test_statistics_menu_and_page_are_hidden_from_other_users_and_guests(): void
    {
        config()->set('kpi.ai_status_viewer_hemis_id', '3172011004');
        $otherUser = User::factory()->create(['hemis_id' => 9999999999]);

        $this->actingAs($otherUser)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('statistics.index'));

        $this->actingAs($otherUser)
            ->get(route('statistics.index'))
            ->assertForbidden();

        auth()->logout();

        $this->get(route('statistics.index'))
            ->assertRedirect(route('login'));
    }

    private function createCriterion(): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Statistika hisoboti'],
            'status' => '1',
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => 'Statistika kriteriyasi'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function createDatumAt(User $owner, Criterion $criterion, string $createdAt): Datum
    {
        $datum = Datum::query()->create([
            'name' => 'Sana bo‘yicha resurs',
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'received',
            'point' => 0,
        ]);

        $createdAt = CarbonImmutable::parse($createdAt, 'Asia/Tashkent');
        $datum->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $datum;
    }
}
