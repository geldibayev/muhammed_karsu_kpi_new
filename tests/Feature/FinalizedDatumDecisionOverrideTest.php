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

class FinalizedDatumDecisionOverrideTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_legacy_ai_decisions_without_ai_history_can_be_reversed_in_both_directions(): void
    {
        [$reviewer, $owner, $criterion] = $this->context('ai');
        $accepted = $this->datum($owner, $criterion, 'accepted', 3, 'Oldingi tasdiq.');
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0, 'Oldingi rad javobi.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $accepted), [
                'reason' => 'Eski qaror qayta tekshirildi.',
            ])
            ->assertRedirect(route('upload.details', $accepted));

        $this->assertSame('cancelled', $accepted->fresh()->status);
        $this->assertSame(0.0, $accepted->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $accepted->getKey(),
            'message_type' => 'human_override_rejected',
        ]);

        foreach ([$accepted, $cancelled] as $datum) {
            $this->actingAs($reviewer)
                ->get(route('upload.details', $datum))
                ->assertOk()
                ->assertSee('Rad etilgan resursni tasdiqlash')
                ->assertSee('0–5.0000');
            $this->actingAs($reviewer)
                ->patch(route('ai-human-reviews.approve-cancelled', $datum), ['point' => 4.25])
                ->assertRedirect(route('upload.details', $datum));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame(4.25, $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'message_type' => 'human_override_approved',
            ]);
        }
    }

    public function test_non_ai_final_decisions_remain_outside_the_ai_override_flow(): void
    {
        [$reviewer, $owner, $criterion] = $this->context('manual');
        $accepted = $this->datum($owner, $criterion, 'accepted', 3, 'Manual tasdiq.');
        $cancelled = $this->datum($owner, $criterion, 'cancelled', 0, 'Manual rad javobi.');

        $this->actingAs($reviewer)
            ->get(route('upload.details', $accepted))
            ->assertOk()
            ->assertDontSee('Tasdiqlangan resursni rad etish');
        $this->actingAs($reviewer)
            ->get(route('upload.details', $cancelled))
            ->assertOk()
            ->assertDontSee('Rad etilgan resursni tasdiqlash');
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.reject-accepted', $accepted), ['reason' => 'Ruxsatsiz.'])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->patch(route('ai-human-reviews.approve-cancelled', $cancelled), ['point' => 2])
            ->assertForbidden();

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    /** @return array{User, User, Criterion} */
    private function context(string $checking): array
    {
        $reviewer = User::factory()->create([
            'hemis_id' => 3172011004,
            'rol' => ['teacher'],
        ]);
        config()->set('kpi.accepted_ai_reviewer_hemis_id', $reviewer->hemis_id);
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
            'name' => ['uz' => 'Qarorlarni tekshirish'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => fake()->unique()->numerify('#.#.#'),
            'name' => ['uz' => 'Kriteriya'],
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'checking' => $checking,
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 5,
        ]);

        return [$reviewer, $owner, $criterion];
    }

    private function datum(
        User $owner,
        Criterion $criterion,
        string $status,
        float $point,
        string $reason,
    ): Datum {
        return Datum::query()->create([
            'name' => fake()->sentence(),
            'material' => [
                'type' => 'url',
                'link' => 'https://example.com/'.fake()->uuid(),
            ],
            'user_id' => $owner->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => $point,
            'reason' => $reason,
        ]);
    }
}
