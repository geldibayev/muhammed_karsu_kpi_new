<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResourceUploadDeadlineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deadline_notice_is_visible_to_guests_and_authenticated_users(): void
    {
        config()->set('kpi.resource_upload_deadline', '2026-08-15 23:59:59');
        $this->travelTo(CarbonImmutable::parse('2026-08-07 12:00:00', 'Asia/Tashkent'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-testid="resource-upload-deadline"', false)
            ->assertSee('2026-yil 15-avgust, 23:59 gacha')
            ->assertSee('Toshkent vaqti');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-testid="resource-upload-deadline"', false)
            ->assertSee('Belgilangan vaqtdan keyin yangi resurslar qabul qilinmaydi.');
    }

    public function test_submission_is_allowed_through_the_final_second_and_blocked_after_deadline(): void
    {
        config()->set('kpi.resource_upload_deadline', '2026-08-15 23:59:59');
        [$criterion, $year] = $this->createUploadableCriterion();
        $teacher = User::factory()->create();

        $this->travelTo(CarbonImmutable::parse('2026-08-15 23:59:59', 'Asia/Tashkent'));

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year))
            ->assertRedirect(route('upload.show', $criterion));

        $this->assertDatabaseCount('data', 1);
        $datum = Datum::query()->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-08-16 00:00:00', 'Asia/Tashkent'));
        Option::setResourceUploadsEnabled(true);

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Muddat yakunlangan, yangi resurs yuklash yopilgan.')
            ->assertDontSee(route('upload.show', $criterion));

        $this->actingAs($teacher)
            ->get(route('upload.show', $criterion))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete(route('upload.destroy', $datum))
            ->assertForbidden();

        $this->assertDatabaseCount('data', 1);
        $this->assertSame('received', $datum->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'submission_deleted',
        ]);
    }

    public function test_super_admin_cannot_bypass_expired_upload_deadline(): void
    {
        config()->set('kpi.resource_upload_deadline', '2026-08-15 23:59:59');
        config()->set('kpi.settings_manager_hemis_id', '3172011004');
        [$criterion, $year] = $this->createUploadableCriterion();
        $superAdmin = User::factory()->superAdmin()->create(['hemis_id' => 3172011004]);
        $datum = Datum::query()->create([
            'name' => 'Avval yuklangan resurs',
            'material' => ['type' => 'url', 'url' => 'https://example.com/existing-resource'],
            'user_id' => User::factory()->create()->getKey(),
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'status' => 'received',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-08-16 00:00:00', 'Asia/Tashkent'));

        $this->actingAs($superAdmin)
            ->post(route('upload.store', $criterion), $this->submissionPayload($year))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->delete(route('upload.destroy', $datum))
            ->assertForbidden();

        $this->assertDatabaseCount('data', 1);
        $this->assertSame('received', $datum->fresh()->status);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->getKey(),
            'message_type' => 'submission_deleted',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Yuklash muddati yakunlangan')
            ->assertSee('Global sozlama yoqilgan bo‘lsa ham yangi resurslar qabul qilinmaydi.');
    }

    /** @return array{Criterion, Year} */
    private function createUploadableCriterion(): array
    {
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Faol hisobot'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => 'Test mezoni'],
            'desc' => ['uz' => 'Test tavsifi'],
            'report_id' => $report->getKey(),
            'upload' => '1',
            'status' => '1',
            'res_type' => 'url',
            'checking' => 'manual',
            'template' => '0',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
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
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$criterion, $year];
    }

    /** @return array<string, int|string> */
    private function submissionPayload(Year $year): array
    {
        return [
            'uploadResourceType' => 'url',
            'uploadResourceUrl' => 'https://example.com/resource',
            'year' => $year->getKey(),
        ];
    }
}
