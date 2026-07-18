<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\CriterionReviewerAssignmentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualReviewWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assignment_seeder_stores_all_hemis_mappings_without_local_users(): void
    {
        $report = $this->createReport();

        foreach ([2, 6, 7, 8, 15, 23, 25, 26, 41] as $criterionId) {
            Criterion::query()->create([
                'id' => $criterionId,
                'name' => ['uz' => 'Mezon '.$criterionId],
                'report_id' => $report->id,
            ]);
        }

        $this->seed(CriterionReviewerAssignmentSeeder::class);
        $this->seed(CriterionReviewerAssignmentSeeder::class);

        $this->assertDatabaseCount('criterion_reviewer_assignments', 9);
        $this->assertDatabaseHas('criterion_reviewer_assignments', [
            'hemis_id' => 3172011004,
            'criterion_id' => 2,
            'criterion_code' => '1/2',
        ]);
        $this->assertNull(CriterionReviewerAssignment::query()->firstOrFail()->user);
    }

    public function test_all_authenticated_users_can_open_responsible_people_page(): void
    {
        $criterion = $this->createCriterion();
        $criterion->update(['name' => ['uz' => 'Biriktirilgan mezon']]);
        $unassignedCriterion = $this->createCriterion();
        $unassignedCriterion->update(['name' => ['uz' => 'Biriktirilmagan mezon']]);
        $integratedCriterion = $this->createCriterion();
        $integratedCriterion->update([
            'name' => ['uz' => 'Integratsion mezon'],
            'checking' => 'department',
        ]);
        $aiCriterion = $this->createCriterion();
        $aiCriterion->update([
            'name' => ['uz' => 'AI mezon'],
            'checking' => 'ai',
        ]);
        $superAdmin = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        $this->assign($superAdmin, $criterion, '1/'.$criterion->id);

        $this->get(route('reviewer-assignments.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($teacher)
            ->get(route('reviewer-assignments.index'))
            ->assertOk()
            ->assertSee('Ma’sullar')
            ->assertSee(route('reviewer-assignments.index'))
            ->assertSee('Mezon raqami')
            ->assertSee('Mezon nomi')
            ->assertSee('Ma’sul F.I.O.')
            ->assertSee('1/'.$criterion->id)
            ->assertSee('Biriktirilgan mezon')
            ->assertSee('Integratsion mezon')
            ->assertSee($superAdmin->full)
            ->assertSee('Biriktirilmagan')
            ->assertDontSee('AI mezon')
            ->assertDontSee((string) $superAdmin->hemis_id);
    }

    public function test_reviewer_queue_contains_only_assigned_pending_submissions(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create();
        $assignedCriterion = $this->createCriterion();
        $otherCriterion = $this->createCriterion();
        $this->assign($reviewer, $assignedCriterion, '1/'.$assignedCriterion->id);
        $assignedDatum = $this->createDatum($owner, $assignedCriterion, ['name' => 'Biriktirilgan resurs']);
        $this->createDatum($owner, $otherCriterion, ['name' => 'Boshqa resurs']);

        $this->actingAs($reviewer)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee(route('reviews.index'))
            ->assertSee('Biriktirilgan resurs')
            ->assertDontSee('Boshqa resurs');

        $this->actingAs(User::factory()->create())
            ->get(route('reviews.index'))
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $assignedDatum))
            ->assertOk();
    }

    public function test_assigned_reviewer_can_download_submission_but_cannot_delete_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/manual-proof.pdf', 'proof');

        $reviewer = User::factory()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $this->assign($reviewer, $criterion, '1/'.$criterion->id);
        $datum = $this->createDatum($owner, $criterion, [
            'name' => 'manual-proof.pdf',
            'material' => ['type' => 'file', 'path' => 'uploads/manual-proof.pdf'],
        ]);

        $this->actingAs($reviewer)
            ->get(route('upload.file.download', $datum))
            ->assertDownload('manual-proof.pdf');
        $this->actingAs($reviewer)
            ->delete(route('upload.destroy', $datum))
            ->assertForbidden();
    }

    public function test_approval_uses_degree_score_records_audit_and_recalculates_report_points(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $this->assign($reviewer, $criterion, '1/'.$criterion->id);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 6,
        ]);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 6,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'message_type' => 'manual_review_approved',
        ]);
        $this->assertSame(6.0, (float) Point::query()
            ->where('user_id', $owner->id)
            ->where('criterion_id', $criterion->id)
            ->value('point'));
    }

    public function test_approval_without_degree_score_does_not_change_submission(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $this->assign($reviewer, $criterion, '1/'.$criterion->id);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum))
            ->assertRedirect(route('reviews.show', $datum))
            ->assertSessionHasErrors('datum');

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'received',
            'point' => 0,
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_rejection_requires_reason_and_records_reviewer_decision(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $this->assign($reviewer, $criterion, '1/'.$criterion->id);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.reject', $datum), ['reason' => ''])
            ->assertRedirect(route('reviews.show', $datum))
            ->assertSessionHasErrors('reason');

        $reason = 'Hujjatdagi ma’lumotlar mezon talabiga mos emas.';
        $this->actingAs($reviewer)
            ->patch(route('reviews.reject', $datum), ['reason' => $reason])
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'cancelled',
            'point' => 0,
            'reason' => $reason,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'message' => $reason,
            'message_type' => 'manual_review_rejected',
        ]);

        $this->actingAs($reviewer)
            ->patch(route('reviews.reject', $datum), ['reason' => 'Ikkinchi qaror'])
            ->assertForbidden();
    }

    private function createReport(): Report
    {
        return Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
    }

    private function createCriterion(): Criterion
    {
        $report = $this->createReport();
        $formula = Formula::query()->create([
            'name' => ['uz' => 'Maksimal ball'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Asosiy bo‘lim'],
            'report_id' => $report->id,
            'formula_id' => $formula->id,
        ]);

        return Criterion::query()->create([
            'name' => ['uz' => 'Manual test mezoni'],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'formula_id' => $formula->id,
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ]);
    }

    private function assign(User $reviewer, Criterion $criterion, string $code): CriterionReviewerAssignment
    {
        return CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->id,
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => $code,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createDatum(User $owner, Criterion $criterion, array $attributes = []): Datum
    {
        return Datum::query()->create(array_merge([
            'name' => 'Test resursi',
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'received',
        ], $attributes));
    }
}
