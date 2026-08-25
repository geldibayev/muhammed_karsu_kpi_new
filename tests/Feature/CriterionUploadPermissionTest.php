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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CriterionUploadPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_migration_can_resume_after_mariadb_leaves_the_created_table_behind(): void
    {
        Schema::table('criterion_upload_permissions', function (Blueprint $table): void {
            $table->dropUnique('cup_user_criterion_active_unique');
        });

        $migration = require database_path('migrations/2026_08_21_163639_create_criterion_upload_permissions_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'criterion_upload_permissions',
            ['user_id', 'criterion_id', 'active_key'],
            'unique',
        ));
    }

    public function test_manager_can_grant_late_upload_access_for_multiple_criteria(): void
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
            ->assertSee('name="criterion_ids[]"', false)
            ->assertSee('name="all_criteria"', false)
            ->assertSee('Barcha mos kriteriyalar')
            ->assertDontSee('name="hemis_id"', false)
            ->assertSee($teacher->full)
            ->assertSee('plugins/select2/js/select2.full.min.js');

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion, $otherCriterion))
            ->assertRedirect()
            ->assertSessionHas('success', 'Foydalanuvchiga 2 ta kriteriya uchun yuklash ruxsati berildi.');

        $permissions = CriterionUploadPermission::query()->orderBy('criterion_id')->get();
        $this->assertCount(2, $permissions);
        $this->assertEqualsCanonicalizing(
            [$criterion->id, $otherCriterion->id],
            $permissions->pluck('criterion_id')->all(),
        );
        $this->assertTrue($permissions->every(fn (CriterionUploadPermission $permission): bool => $permission->user_id === $teacher->id
            && $permission->granted_by_user_id === $manager->id
            && $permission->reason === 'Scopus indeksatsiyasi kech tasdiqlandi.'));

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Sizga maxsus ruxsat berilgan kriteriyalarda yuklash tugmasi faol.')
            ->assertSee(route('upload.show', $criterion))
            ->assertSee(route('upload.show', $otherCriterion));
        $this->actingAs($teacher)->get(route('upload.show', $criterion))->assertOk();
        $this->actingAs($teacher)->get(route('upload.show', $otherCriterion))->assertOk();
        $this->actingAs($otherTeacher)->get(route('upload.show', $criterion))->assertForbidden();
    }

    public function test_permission_allows_only_the_remaining_criterion_file_limit(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        config()->set('kpi.resource_upload_deadline', now()->addDay()->toDateTimeString());
        Option::setResourceUploadsEnabled(true);
        $manager = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        [$criterion, $year] = $this->createUploadableCriterion('Uchta resursli kriteriya', fileLimit: 3);

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year, 1))
            ->assertRedirect(route('upload.show', $criterion));

        config()->set('kpi.resource_upload_deadline', now()->subDay()->toDateTimeString());
        Option::setResourceUploadsEnabled(false);
        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $criterion))
            ->assertRedirect();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year, 2))
            ->assertRedirect(route('upload.show', $criterion));
        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year, 3))
            ->assertRedirect(route('upload.show', $criterion));

        $permission = CriterionUploadPermission::query()->sole();
        $this->assertTrue($permission->active_key);
        $this->assertNotNull($permission->used_at);
        $this->assertNotNull($permission->datum_id);
        $this->assertSame(2, DB::table('datum_histories')
            ->where('message_type', 'criterion_upload_permission_used')
            ->count());

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year, 4))
            ->assertSessionHasErrors('uploadResourceFile');

        $this->assertDatabaseCount('data', 3);
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

        [$otherCriterion] = $this->createUploadableCriterion('Ikkinchi mos kriteriya', $criterion->report);
        $criterion->criterionEvaluations()->update(['has' => '0']);
        $this->actingAs($manager)
            ->from(route('settings.index'))
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $otherCriterion, $criterion))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('criterion_ids');
        $this->assertDatabaseMissing('criterion_upload_permissions', [
            'user_id' => $teacher->id,
            'criterion_id' => $otherCriterion->id,
            'active_key' => true,
        ]);
        $this->actingAs($manager)
            ->from(route('settings.index'))
            ->post(route('settings.upload-permissions.store'), [
                'user_id' => 9999999999,
                'criterion_ids' => [$criterion->id],
                'reason' => 'Noto‘g‘ri foydalanuvchi.',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('user_id');
    }

    public function test_manager_can_grant_all_eligible_criteria_without_duplicate_permissions(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $manager = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        [$firstCriterion] = $this->createUploadableCriterion('Birinchi kriteriya');
        [$secondCriterion] = $this->createUploadableCriterion('Ikkinchi kriteriya', $firstCriterion->report);
        [$ineligibleCriterion] = $this->createUploadableCriterion('Mos bo‘lmagan kriteriya', $firstCriterion->report);
        $ineligibleCriterion->criterionEvaluations()->update(['has' => '0']);

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $firstCriterion))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), [
                'user_id' => $teacher->id,
                'all_criteria' => '1',
                'reason' => 'Barcha mos kriteriyalar uchun ruxsat.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Foydalanuvchiga 1 ta kriteriya uchun yuklash ruxsati berildi.');

        $this->assertEqualsCanonicalizing(
            [$firstCriterion->id, $secondCriterion->id],
            CriterionUploadPermission::query()->pluck('criterion_id')->all(),
        );
        $this->assertDatabaseMissing('criterion_upload_permissions', [
            'user_id' => $teacher->id,
            'criterion_id' => $ineligibleCriterion->id,
            'active_key' => true,
        ]);
    }

    public function test_permission_can_only_be_granted_for_the_current_report_used_by_home(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        config()->set('kpi.resource_upload_deadline', now()->subDay()->toDateTimeString());
        Option::setResourceUploadsEnabled(false);
        $manager = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        [$oldCriterion] = $this->createUploadableCriterion('Eski faol hisobot kriteriyasi');
        [$currentCriterion, $currentYear] = $this->createUploadableCriterion('Joriy hisobot kriteriyasi');

        $this->actingAs($manager)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('Eski faol hisobot kriteriyasi')
            ->assertSee('Joriy hisobot kriteriyasi');

        $this->actingAs($manager)
            ->from(route('settings.index'))
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $oldCriterion))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('criterion_ids');

        CriterionUploadPermission::query()->create([
            'user_id' => $teacher->id,
            'criterion_id' => $oldCriterion->id,
            'granted_by_user_id' => $manager->id,
            'reason' => 'Eski noto‘g‘ri ruxsat.',
            'active_key' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('upload.show', $oldCriterion))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('settings.upload-permissions.store'), $this->grantPayload($teacher, $currentCriterion))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('upload.show', $currentCriterion))
            ->assertDontSee(route('upload.show', $oldCriterion));
        $this->actingAs($teacher)
            ->get(route('upload.show', $currentCriterion))
            ->assertOk();
        $this->actingAs($teacher)
            ->post(route('upload.store', $currentCriterion), $this->submissionPayload($currentYear, 1))
            ->assertRedirect(route('upload.show', $currentCriterion))
            ->assertSessionHasNoErrors();
    }

    /** @return array<string, array<int, int>|int|string> */
    private function grantPayload(User $user, Criterion ...$criteria): array
    {
        return [
            'user_id' => $user->id,
            'criterion_ids' => collect($criteria)
                ->map(static fn (Criterion $criterion): int => $criterion->id)
                ->all(),
            'reason' => 'Scopus indeksatsiyasi kech tasdiqlandi.',
        ];
    }

    /** @return array<string, int|string> */
    private function submissionPayload(Year $year, int $sequence): array
    {
        return [
            'uploadResourceType' => 'url',
            'uploadResourceUrl' => "https://example.com/resource-{$sequence}",
            'year' => $year->id,
        ];
    }

    /** @return array{Criterion, Year} */
    private function createUploadableCriterion(
        string $name,
        ?Report $report = null,
        int $fileLimit = 0,
    ): array {
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
            'file_limit' => $fileLimit,
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
