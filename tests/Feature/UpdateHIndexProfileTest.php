<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UpdateHIndexProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_view_and_update_each_h_index_profile_with_recalculation(): void
    {
        [$owner, $datum] = $this->context();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Scopus')
            ->assertSee('Web of Science')
            ->assertSee('ResearchGate')
            ->assertSee('https://wos.example/profile')
            ->assertSee('id="h-index-web_of_science"', false)
            ->assertDontSee('Ballni o‘zgartirish');

        $this->actingAs($superAdmin)
            ->patch(route('submissions.accepted-score.update', $datum), [
                'point' => 99,
                'score_change_reason' => 'Qo‘lda ball berish.',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->patch(route('submissions.h-index-profile.update', $datum), [
                'profile' => 'web_of_science',
                'expected_value' => 0,
                'new_value' => 5,
            ])
            ->assertRedirect(route('upload.details', $datum))
            ->assertSessionHasNoErrors();

        $datum->refresh();
        $this->assertSame(5, data_get($datum->material, 'profiles.web_of_science.value'));
        $this->assertSame(5.25, $datum->point);
        $this->assertSame(5.25, (float) Point::query()
            ->where('user_id', $owner->getKey())
            ->where('criterion_id', $datum->criterion_id)
            ->value('point'));
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'user_id' => $superAdmin->getKey(),
            'message_type' => 'h_index_profile_corrected',
        ]);
    }

    public function test_non_super_admin_cannot_update_h_index_profile(): void
    {
        [, $datum] = $this->context();
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->patch(route('submissions.h-index-profile.update', $datum), [
                'profile' => 'web_of_science',
                'expected_value' => 0,
                'new_value' => 5,
            ])
            ->assertForbidden();

        $this->assertSame(0, data_get($datum->fresh()->material, 'profiles.web_of_science.value'));
    }

    public function test_migration_recalculates_existing_zero_h_index_points(): void
    {
        [, $datum] = $this->context();
        $datum->update(['point' => 3]);

        $migration = require database_path('migrations/2026_08_13_183554_recalculate_h_index_points_for_zero_values.php');
        $migration->up();

        $this->assertSame(2.25, $datum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'h_index_score_recalculated',
        ]);
    }

    /** @return array{User, Datum} */
    private function context(): array
    {
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => 'H-index hisoboti'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Ilmiy faoliyat'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => Criterion::H_INDEX_CODE,
            'name' => ['uz' => 'H-index'],
            'report_id' => $report->getKey(),
            'parent_id' => $parent->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 3,
        ]);
        $datum = Datum::query()->create([
            'name' => 'H-index resursi',
            'material' => [
                'type' => 'h_index',
                'profiles' => [
                    'scopus' => ['link' => 'https://scopus.example/profile', 'value' => 4],
                    'web_of_science' => ['link' => 'https://wos.example/profile', 'value' => 0],
                    'research_gate' => ['link' => 'https://researchgate.example/profile', 'value' => 2],
                ],
            ],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 3,
        ]);

        return [$owner, $datum];
    }
}
