<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_configured_hemis_user_can_view_and_update_upload_settings(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $manager = User::factory()->create(['hemis_id' => 3172011004]);
        $otherSuperAdmin = User::factory()->superAdmin()->create(['hemis_id' => 9999999999]);

        $this->actingAs($manager)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('settings.index'));

        $this->actingAs($manager)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Yuklashga ruxsat berilgan');

        $this->actingAs($manager)
            ->put(route('settings.uploads.update'), ['resource_uploads_enabled' => '0'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tizimga resurs yuklash vaqtincha o‘chirildi.');

        $this->assertDatabaseHas('options', [
            'key' => Option::RESOURCE_UPLOADS_ENABLED,
            'value' => '0',
        ]);

        $this->actingAs($otherSuperAdmin)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('settings.index'));
        $this->actingAs($otherSuperAdmin)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($otherSuperAdmin)
            ->put(route('settings.uploads.update'), ['resource_uploads_enabled' => '1'])
            ->assertForbidden();

        auth()->logout();

        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_disabled_setting_blocks_all_resource_submissions_until_reenabled(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $manager = User::factory()->create(['hemis_id' => 3172011004]);
        $teacher = User::factory()->create();
        [$criterion, $year] = $this->createUploadableCriterion();

        $this->actingAs($manager)
            ->put(route('settings.uploads.update'), ['resource_uploads_enabled' => '0'])
            ->assertRedirect();

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Tizimga resurs yuklash administrator tomonidan vaqtincha to‘xtatilgan.')
            ->assertDontSee(route('upload.show', $criterion));

        $this->actingAs($teacher)
            ->get(route('upload.show', $criterion))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/resource',
                'year' => $year->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('data', 0);

        $this->actingAs($manager)
            ->put(route('settings.uploads.update'), ['resource_uploads_enabled' => '1'])
            ->assertRedirect();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/resource',
                'year' => $year->id,
            ])
            ->assertRedirect(route('upload.show', $criterion));

        $this->assertDatabaseCount('data', 1);
    }

    public function test_upload_setting_requires_a_boolean_value(): void
    {
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        $manager = User::factory()->create(['hemis_id' => 3172011004]);

        $this->actingAs($manager)
            ->put(route('settings.uploads.update'), ['resource_uploads_enabled' => 'invalid'])
            ->assertSessionHasErrors('resource_uploads_enabled');

        $this->assertTrue(Option::resourceUploadsEnabled());
    }

    /** @return array{Criterion, Year} */
    private function createUploadableCriterion(): array
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Test hisoboti'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Test mezoni'],
            'desc' => ['uz' => 'Test tavsifi'],
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
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);
        DB::table('criterion_years')->insert([
            'criterion_id' => $criterion->id,
            'year_id' => $year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$criterion, $year];
    }
}
