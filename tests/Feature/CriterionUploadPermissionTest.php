<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionUploadPermission;
use App\Models\Evaluation;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriterionUploadPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_can_grant_one_late_upload_to_one_user_and_criterion(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        config()->set('kpi.resource_upload_deadline', now()->subDay()->toDateTimeString());
        Option::setResourceUploadsEnabled(false);
        $manager = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        [$criterion, $year] = $this->createUploadableCriterion('Maxsus Scopus kriteriyasi');
        [$otherCriterion] = $this->createUploadableCriterion('Boshqa kriteriya', $criterion->report);

        $this->actingAs($otherTeacher)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Maxsus yuklash ruxsati')
            ->assertSee('Maxsus Scopus kriteriyasi')
            ->assertSee('name="user_id"', false)
            ->assertDontSee('name="hemis_id"', false)
            ->assertSee($teacher->full)
            ->assertSee('plugins/select2/js/select2.full.min.js');

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion))
            ->assertRedirect()
            ->assertSessionHas('success', 'Foydalanuvchiga tanlangan kriteriya uchun yuklash ruxsati berildi.');

        $permission = CriterionUploadPermission::query()->sole();
        $this->assertSame($teacher->id, $permission->user_id);
        $this->assertSame($criterion->id, $permission->criterion_id);
        $this->assertSame($manager->id, $permission->granted_by_user_id);
        $this->assertSame('Scopus indeksatsiyasi kech tasdiqlandi.', $permission->reason);

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Sizga maxsus ruxsat berilgan kriteriyalarda yuklash tugmasi faol.')
            ->assertSee(route('upload.show', $criterion))
            ->assertDontSee(route('upload.show', $otherCriterion));
        $this->actingAs($teacher)->get(route('upload.show', $criterion))->assertOk();
        $this->actingAs($teacher)->get(route('upload.show', $otherCriterion))->assertForbidden();
        $this->actingAs($otherTeacher)->get(route('upload.show', $criterion))->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/new-scopus-article',
                'year' => $year->id,
            ])
            ->assertRedirect(route('upload.show', $criterion));

        $permission->refresh();
        $this->assertNull($permission->active_key);
        $this->assertNotNull($permission->used_at);
        $this->assertNotNull($permission->datum_id);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $permission->datum_id,
            'message_type' => 'criterion_upload_permission_used',
        ]);
        $this->actingAs($teacher)->get(route('upload.show', $criterion))->assertForbidden();
        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/second-article',
                'year' => $year->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('data', 1);
    }

    public function test_manager_can_revoke_unused_permission_and_invalid_grants_are_rejected(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $manager = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        [$criterion] = $this->createUploadableCriterion('Mos kriteriya');

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion))
            ->assertRedirect();
        $permission = CriterionUploadPermission::query()->sole();

        $this->actingAs($manager)
            ->delete(route('settings.upload-permissions.destroy', $permission))
            ->assertRedirect()
            ->assertSessionHas('success', 'Maxsus yuklash ruxsati bekor qilindi.');
        $permission->refresh();
        $this->assertNull($permission->active_key);
        $this->assertNotNull($permission->revoked_at);
        $this->assertSame($manager->id, $permission->revoked_by_user_id);

        $criterion->criterionEvaluations()->update(['has' => '0']);
        $this->actingAs($manager)
            ->from(route('settings.index'))
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('criterion_id');
        $this->actingAs($manager)
            ->from(route('settings.index'))
            ->post(route('settings.upload-permissions.store'), [
                'user_id' => 9999999999,
                'criterion_id' => $criterion->id,
                'reason' => 'Noto‘g‘ri foydalanuvchi.',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('user_id');
    }

    /** @return array<string, int|string> */
    private function grantPayload(User $user, Criterion $criterion): array
    {
        return [
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'reason' => 'Scopus indeksatsiyasi kech tasdiqlandi.',
        ];
    }

    /** @return array{Criterion, Year} */
    private function createUploadableCriterion(string $name, ?Report $report = null): array
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report ??= Report::query()->create([
            'name' => ['uz' => $name.' hisoboti'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Asosiy bo‘lim'],
            'report_id' => $report->id,
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => $name],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'upload' => '1',
            'status' => '1',
            'res_type' => 'url',
            'checking' => 'manual',
            'template' => '0',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 10,
        ]);
        $year = Year::query()->firstOrCreate(
            ['id' => 2026],
            ['name' => '2026', 'status' => '1'],
        );
        DB::table('criterion_years')->insert([
            'criterion_id' => $criterion->id,
            'year_id' => $year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$criterion, $year];
    }
}
