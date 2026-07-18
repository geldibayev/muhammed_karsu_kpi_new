<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthorizationBoundariesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_super_admin_can_edit_and_update_a_criterion(): void
    {
        $criterion = $this->createCriterion();
        $teacher = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($teacher)
            ->get(route('criteria.edit', $criterion))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->put(route('criteria.update', $criterion), ['ai_prompt' => 'Noqonuniy o‘zgarish'])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->put(route('criteria.update', $criterion), ['ai_prompt' => 'Yangi tekshiruv prompti'])
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('criteria', [
            'id' => $criterion->id,
            'ai_prompt' => 'Yangi tekshiruv prompti',
        ]);
    }

    public function test_submission_download_and_delete_are_limited_to_the_owner(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/proof.pdf', 'proof');

        $criterion = $this->createCriterion();
        $owner = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $datum = Datum::query()->create([
            'name' => 'proof.pdf',
            'material' => ['type' => 'file', 'path' => 'uploads/proof.pdf'],
            'user_id' => $owner->id,
            'criterion_id' => $criterion->id,
            'status' => 'received',
        ]);

        $this->actingAs($otherTeacher)
            ->get(route('upload.file.download', $datum))
            ->assertForbidden();
        $this->actingAs($otherTeacher)
            ->delete(route('upload.destroy', $datum))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('upload.file.download', $datum))
            ->assertDownload('proof.pdf');
        $this->actingAs($owner)
            ->delete(route('upload.destroy', $datum))
            ->assertRedirect();

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'deleted',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'submission_deleted',
        ]);
        Storage::disk('public')->assertMissing('uploads/proof.pdf');
    }

    public function test_teacher_cannot_submit_to_an_inactive_criterion(): void
    {
        $criterion = $this->createCriterion(['status' => '0']);
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get(route('upload.show', $criterion))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(array $attributes = []): Criterion
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);

        return Criterion::query()->create(array_merge([
            'name' => ['uz' => 'Test mezoni'],
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
        ], $attributes));
    }
}
