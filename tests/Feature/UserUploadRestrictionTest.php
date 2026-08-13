<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserUploadRestrictionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_block_and_unblock_user_uploads_with_a_visible_reason(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Test kriteriyasi'],
            'report_id' => $report->getKey(),
            'upload' => '1',
            'status' => '1',
        ]);
        $reason = 'Soxtalashtirilgan hujjat yuklangani sababli vaqtincha bloklandi.';

        $this->actingAs($superAdmin)
            ->patch(route('users.upload-restriction.update', $user), ['blocked' => 1])
            ->assertSessionHasErrors('reason');

        $this->actingAs($superAdmin)
            ->patch(route('users.upload-restriction.update', $user), [
                'blocked' => 1,
                'reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->isUploadBlocked());
        $this->assertSame($reason, $user->upload_block_reason);
        $this->assertSame($superAdmin->getKey(), $user->upload_blocked_by_user_id);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('alert alert-danger', false)
            ->assertSee($reason);
        $this->actingAs($user)->get(route('upload.show', $criterion))->assertForbidden();
        $this->actingAs($user)->post(route('upload.store', $criterion))->assertForbidden();

        $this->actingAs($superAdmin)
            ->patch(route('users.upload-restriction.update', $user), ['blocked' => 0])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->isUploadBlocked());
        $this->assertNull($user->upload_block_reason);
        $this->assertNull($user->upload_blocked_by_user_id);
    }

    public function test_non_super_admin_cannot_manage_upload_restrictions(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->patch(route('users.upload-restriction.update', $target), [
                'blocked' => 1,
                'reason' => 'Ruxsatsiz urinish.',
            ])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->isUploadBlocked());
    }
}
