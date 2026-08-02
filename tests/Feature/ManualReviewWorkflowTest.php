<?php

namespace Tests\Feature;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\CriterionManualScoreOptionSeeder;
use Database\Seeders\CriterionReviewerAssignmentSeeder;
use Database\Seeders\OavCriterionRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualReviewWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assignment_seeder_stores_all_hemis_mappings_without_local_users(): void
    {
        $report = $this->createReport();
        Criterion::query()->create([
            'id' => 1,
            'code' => '1',
            'name' => ['uz' => 'Bo‘lim'],
            'report_id' => $report->id,
        ]);

        $criterionCodes = [
            2 => '1.1',
            6 => '1.5',
            7 => '1.6',
            8 => '1.7',
            15 => '2.1.3',
            16 => '2.1.4',
            23 => '3.1.4',
            25 => '3.1.6',
            26 => '3.1.7',
            36 => '4.1.1',
            41 => '4.1.6',
        ];

        foreach ($criterionCodes as $criterionId => $criterionCode) {
            Criterion::query()->create([
                'id' => $criterionId,
                'code' => $criterionCode,
                'name' => [
                    'uz' => $criterionId === 36
                        ? 'OAV yoki ijtimoiy tarmoqlarda universitet va mamlakatda amalga oshirilayotgan islohotlar yuzasidan chiqishlar qilganlig'
                        : 'Mezon '.$criterionId,
                ],
                'parent_id' => 1,
                'report_id' => $report->id,
            ]);
        }

        $this->seed(CriterionReviewerAssignmentSeeder::class);
        $this->seed(CriterionReviewerAssignmentSeeder::class);
        $this->seed(CriterionManualScoreOptionSeeder::class);
        $this->seed(CriterionManualScoreOptionSeeder::class);

        $this->assertDatabaseCount('criterion_reviewer_assignments', 11);
        $this->assertDatabaseHas('criterion_reviewer_assignments', [
            'hemis_id' => 3172011004,
            'criterion_id' => 2,
            'criterion_code' => '1.1',
        ]);
        $this->assertDatabaseHas('criterion_reviewer_assignments', [
            'hemis_id' => 3172011004,
            'criterion_id' => 36,
            'criterion_code' => '4.1.1',
        ]);
        $this->assertDatabaseHas('criterion_reviewer_assignments', [
            'hemis_id' => 3462611061,
            'criterion_id' => 16,
            'criterion_code' => '2.1.4',
        ]);
        $oavAssignment = CriterionReviewerAssignment::query()
            ->where('criterion_code', '4.1.1')
            ->firstOrFail();
        $this->assertNull($oavAssignment->user);

        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $this->assertTrue($oavAssignment->fresh()->user->is($reviewer));
        $this->assertDatabaseCount('criterion_manual_score_options', 12);

        $expectedScoreOptions = [
            [2, 'video_lesson', 1.5],
            [2, 'video_clip', 1],
            [2, 'presentation', 0.5],
            [15, 'a1', 0.5],
            [15, 'a2', 0.5],
            [15, 'b1', 0.75],
            [15, 'b2', 1],
            [15, 'c1', 1.5],
            [15, 'c2', 2],
            [16, 'rector_order', 1],
            [25, 'dsc_diploma', 3],
            [26, 'phd_diploma', 3],
        ];

        foreach ($expectedScoreOptions as [$criterionId, $code, $point]) {
            $this->assertDatabaseHas('criterion_manual_score_options', [
                'criterion_id' => $criterionId,
                'code' => $code,
                'point' => $point,
            ]);
        }
    }

    public function test_oav_rule_seeder_configures_fixed_manual_scoring_idempotently(): void
    {
        $report = $this->createReport();
        $competitionFormula = Formula::query()->create([
            'id' => 1,
            'code' => Formula::Competition,
            'name' => ['uz' => 'Raqobat reyting tizimida'],
            'status' => '1',
        ]);
        Formula::query()->create([
            'id' => 2,
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal ballga asoslangan'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'Ijtimoiy-ma’naviy faoliyat'],
            'report_id' => $report->id,
            'formula_id' => 1,
        ]);
        $criterion = Criterion::query()->create([
            'code' => '4.1.1',
            'name' => [
                'uz' => 'OAV yoki ijtimoiy tarmoqlarda universitet va mamlakatda amalga oshirilayotgan islohotlar yuzasidan chiqishlar qilganlig',
            ],
            'parent_id' => $parent->id,
            'report_id' => $report->id,
            'formula_id' => 1,
            'checking' => 'ai',
            'file_limit' => 0,
            'upload' => '1',
            'status' => '1',
        ]);
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $this->assign($reviewer, $criterion, '4.1.1');
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 2,
        ]);

        $this->seed(OavCriterionRuleSeeder::class);
        $this->seed(OavCriterionRuleSeeder::class);

        $criterion->refresh();
        $this->assertSame('manual', $criterion->checking);
        $this->assertSame(4, $criterion->file_limit);
        $this->assertSame($competitionFormula->getKey(), $criterion->formula_id);
        $this->assertDatabaseHas('criterion_evaluations', [
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'score' => 3,
        ]);
        $this->assertDatabaseCount('criterion_manual_score_options', 1);
        $this->assertDatabaseHas('criterion_manual_score_options', [
            'criterion_id' => $criterion->id,
            'code' => 'approved_resource',
            'point' => 0.75,
            'active' => true,
        ]);

        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertSee('Rad etish')
            ->assertDontSee('id="score-option"', false)
            ->assertDontSee('name="score_option_id"', false);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertRedirect(route('reviews.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 0.75,
        ]);

        foreach (range(1, 3) as $index) {
            $additionalDatum = $this->createDatum($owner, $criterion, [
                'name' => 'Qo‘shimcha OAV resursi '.$index,
            ]);

            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $additionalDatum))
                ->assertRedirect(route('reviews.index'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(4, Datum::query()
            ->where('user_id', $owner->id)
            ->where('criterion_id', $criterion->id)
            ->where('status', 'accepted')
            ->where('point', 0.75)
            ->count());
        $this->assertSame(3.0, (float) Point::query()
            ->where('user_id', $owner->id)
            ->where('criterion_id', $criterion->id)
            ->value('point'));
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
            'name' => ['uz' => 'Biriktirilgan AI mezon'],
            'checking' => 'ai',
        ]);
        $unassignedAiCriterion = $this->createCriterion();
        $unassignedAiCriterion->update([
            'name' => ['uz' => 'Biriktirilmagan AI mezon'],
            'checking' => 'ai',
        ]);
        $superAdmin = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        $this->assign($superAdmin, $criterion, '1/'.$criterion->id);
        $this->assign($superAdmin, $aiCriterion, '4/'.$aiCriterion->id);

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
            ->assertSee('Biriktirilgan AI mezon')
            ->assertSee('Integratsion mezon')
            ->assertSee($superAdmin->full)
            ->assertSee('Biriktirilmagan')
            ->assertDontSee('Biriktirilmagan AI mezon')
            ->assertDontSee((string) $superAdmin->hemis_id);
    }

    public function test_assigned_ai_criterion_only_exposes_ai_human_review_results(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $otherReviewer = User::factory()->create();
        $unassignedUser = User::factory()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $criterion->update([
            'name' => ['uz' => '4/36 OAV kriteriyasi'],
            'checking' => 'ai',
        ]);
        $this->assignAiHumanReviewer($reviewer);

        $received = $this->createDatum($owner, $criterion, [
            'name' => 'Yuborilgan OAV resursi',
            'status' => 'received',
        ]);
        $checking = $this->createDatum($owner, $criterion, [
            'name' => 'Tekshirilayotgan OAV resursi',
            'status' => 'checking',
        ]);
        $humanReview = $this->createDatum($owner, $criterion, [
            'name' => 'Inson tekshiradigan OAV resursi',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $this->createDatum($owner, $criterion, [
            'name' => 'Yakunlangan OAV resursi',
            'status' => 'accepted',
        ]);
        $otherHumanReview = $this->createDatum($owner, $criterion, [
            'name' => 'Boshqa tekshiruvchining OAV resursi',
            'status' => 'checking',
            'reviewer_hemis_id' => $otherReviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee('AI inson tekshiruvi')
            ->assertSee(route('ai-human-reviews.index'))
            ->assertDontSee('href="'.route('reviews.index').'"', false)
            ->assertSee($humanReview->name)
            ->assertDontSee($received->name)
            ->assertDontSee($checking->name)
            ->assertDontSee($otherHumanReview->name)
            ->assertDontSee('Yakunlangan OAV resursi');
        $this->actingAs($reviewer)
            ->get(route('reviews.index'))
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $received))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(route('reviews.show', $checking))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(route('reviews.show', $humanReview))
            ->assertOk()
            ->assertSee(route('ai-human-reviews.index'));
        $this->actingAs($otherReviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee($otherHumanReview->name)
            ->assertDontSee($humanReview->name);
        $this->actingAs($unassignedUser)
            ->get(route('ai-human-reviews.index'))
            ->assertForbidden();
    }

    public function test_database_assigned_ai_reviewer_only_receives_ai_human_review_assignments(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $otherUser = User::factory()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $criterion->update(['checking' => 'ai']);
        $this->assignAiHumanReviewer($reviewer);

        $humanReview = $this->createDatum($owner, $criterion, [
            'name' => 'AI inson tekshiruvi',
            'status' => 'checking',
            'reviewer_hemis_id' => 3172011004,
        ]);
        $processing = $this->createDatum($owner, $criterion, [
            'name' => 'AI hali ishlayapti',
            'status' => 'checking',
        ]);
        $failed = $this->createDatum($owner, $criterion, [
            'name' => 'AI texnik xatosi',
            'status' => 'checking',
        ]);
        $failed->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'AI xizmatida texnik xato.',
            'message_type' => 'ai_failed',
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee('AI inson tekshiruvi')
            ->assertSee($humanReview->name)
            ->assertDontSee($processing->name)
            ->assertDontSee($failed->name);
        $this->actingAs($reviewer)
            ->get(route('reviews.index'))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(route('reviews.show', $humanReview))
            ->assertOk();
        $this->actingAs($reviewer)
            ->get(route('reviews.show', $processing))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->get(route('ai-human-reviews.index'))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->get(route('reviews.show', $humanReview))
            ->assertForbidden();
    }

    public function test_command_assigns_legacy_ai_human_reviews_but_skips_failures_and_transfers(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $oldReviewer = User::factory()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $criterion->update(['checking' => 'ai']);
        $this->assignAiHumanReviewer($reviewer);
        $humanReview = $this->createDatum($owner, $criterion, [
            'name' => 'Eski AI inson tekshiruvi',
            'status' => 'checking',
        ]);
        $humanReview->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'Inson tekshiruvi kerak.',
            'message_type' => 'ai_evaluation',
        ]);
        $previouslyAssigned = $this->createDatum($owner, $criterion, [
            'name' => 'Eski mas’ulga biriktirilgan',
            'status' => 'checking',
            'reviewer_hemis_id' => $oldReviewer->hemis_id,
        ]);
        $previouslyAssigned->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'Inson tekshiruvi kerak.',
            'message_type' => 'ai_evaluation',
        ]);
        $failed = $this->createDatum($owner, $criterion, [
            'name' => 'Eski AI xatosi',
            'status' => 'checking',
        ]);
        $failed->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'Texnik xato.',
            'message_type' => 'ai_failed',
        ]);
        $transferred = $this->createDatum($owner, $criterion, [
            'name' => 'Kriteriyasi almashtirilgan',
            'status' => 'checking',
        ]);
        $transferred->histories()->createMany([
            [
                'user_id' => $owner->id,
                'type' => 'warning',
                'message' => 'Eski AI bahosi.',
                'message_type' => 'ai_evaluation',
            ],
            [
                'user_id' => $owner->id,
                'type' => 'info',
                'message' => 'Kriteriya almashtirildi.',
                'message_type' => 'criterion_transferred',
            ],
        ]);

        $this->artisan('kpi:ai:assign-human-reviews', ['--dry-run' => true])
            ->expectsOutput('AI inson tekshiruvi uchun biriktiriladigan resurslar: 1')
            ->assertSuccessful();
        $this->assertNull($humanReview->fresh()->reviewer_hemis_id);

        $this->artisan('kpi:ai:assign-human-reviews')
            ->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 1')
            ->assertSuccessful();

        $this->assertSame(3172011004, $humanReview->fresh()->reviewer_hemis_id);
        $this->assertSame($oldReviewer->hemis_id, $previouslyAssigned->fresh()->reviewer_hemis_id);
        $this->assertNull($failed->fresh()->reviewer_hemis_id);
        $this->assertNull($transferred->fresh()->reviewer_hemis_id);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $humanReview->id,
            'message_type' => 'ai_human_review_assigned',
        ]);

        $this->artisan('kpi:ai:assign-human-reviews', [
            '--reassign' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('AI inson tekshiruvi uchun biriktiriladigan resurslar: 1')
            ->assertSuccessful();
        $this->artisan('kpi:ai:assign-human-reviews', ['--reassign' => true])
            ->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 1')
            ->assertSuccessful();
        $this->assertSame(3172011004, $previouslyAssigned->fresh()->reviewer_hemis_id);
    }

    public function test_ai_human_review_assignment_command_fails_without_global_reviewer(): void
    {
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $criterion->update(['checking' => 'ai']);
        $humanReview = $this->createDatum($owner, $criterion, ['status' => 'checking']);
        $humanReview->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'Inson tekshiruvi kerak.',
            'message_type' => 'ai_evaluation',
        ]);

        $this->artisan('kpi:ai:assign-human-reviews', ['--dry-run' => true])
            ->expectsOutput('Global AI inson tekshiruvchisi sozlanmagan.')
            ->assertFailed();
        $this->assertNull($humanReview->fresh()->reviewer_hemis_id);
    }

    public function test_global_ai_human_reviewer_can_be_configured_and_changed_by_hemis_id(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $nextReviewer = User::factory()->create();

        $this->artisan('kpi:ai:set-human-reviewer', ['hemis_id' => $reviewer->hemis_id])
            ->expectsOutput("AI inson tekshiruvchisi HEMIS ID {$reviewer->hemis_id} ga o‘zgartirildi.")
            ->assertSuccessful();
        $this->assertSame($reviewer->hemis_id, AiHumanReviewAssignment::activeHemisId());

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee('Inson tekshiruvi uchun AI resurslari yo‘q.');

        $this->artisan('kpi:ai:set-human-reviewer', ['hemis_id' => $reviewer->hemis_id])
            ->expectsOutput("AI inson tekshiruvchisi allaqachon HEMIS ID {$reviewer->hemis_id}.")
            ->assertSuccessful();
        $this->assertDatabaseCount('ai_human_review_assignments', 1);

        $this->artisan('kpi:ai:set-human-reviewer', ['hemis_id' => $nextReviewer->hemis_id])
            ->assertSuccessful();

        $this->assertSame($nextReviewer->hemis_id, AiHumanReviewAssignment::activeHemisId());
        $this->assertDatabaseCount('ai_human_review_assignments', 2);
        $this->assertDatabaseHas('ai_human_review_assignments', [
            'hemis_id' => $reviewer->hemis_id,
            'active_slot' => null,
        ]);
    }

    public function test_global_ai_human_reviewer_command_rejects_unknown_hemis_id(): void
    {
        $this->artisan('kpi:ai:set-human-reviewer', ['hemis_id' => 3172011004])
            ->expectsOutput('HEMIS ID 3172011004 bo‘lgan foydalanuvchi topilmadi.')
            ->assertFailed();

        $this->assertDatabaseCount('ai_human_review_assignments', 0);
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
            ->assertSee('Baholash')
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

    public function test_unassigned_super_admin_cannot_see_or_review_submission(): void
    {
        $reviewer = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $this->assign($reviewer, $criterion, '1/'.$criterion->id);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($superAdmin)
            ->get(route('reviews.index'))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('reviews.index'));
        $this->actingAs($superAdmin)
            ->get(route('reviews.show', $datum))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('reviews.approve', $datum))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->patch(route('reviews.reject', $datum), ['reason' => 'Ruxsatsiz qaror'])
            ->assertForbidden();

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'received',
            'point' => 0,
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'manual_review_rejected',
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'manual_review_approved',
        ]);

        CriterionReviewerAssignment::query()
            ->where('criterion_id', $criterion->id)
            ->update(['hemis_id' => $superAdmin->hemis_id]);

        $this->actingAs($superAdmin)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertSee($datum->name);
        $this->actingAs($superAdmin)
            ->get(route('reviews.show', $datum))
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

    public function test_assigned_reviewer_can_transfer_submission_to_another_criterion_with_audit(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create();
        $sourceCriterion = $this->createCriterion();
        $targetCriterion = $this->createSiblingCriterion($sourceCriterion, 'Tegishli mezon');
        $this->assign($reviewer, $sourceCriterion, '1/'.$sourceCriterion->id);
        $datum = $this->createDatum($owner, $sourceCriterion, [
            'material' => ['type' => 'file', 'path' => 'uploads/proof.pdf'],
            'status' => 'checking',
            'point' => 4.5,
            'reviewer_hemis_id' => $reviewer->hemis_id,
            'reason' => 'Eski baholash sababi',
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Boshqa kriteriyaga o‘tkazish')
            ->assertSee('Tegishli mezon')
            ->assertSee(route('reviews.transfer-criterion', $datum));

        $this->actingAs($reviewer)
            ->patch(route('reviews.transfer-criterion', $datum), [
                'criterion_id' => $targetCriterion->id,
            ])
            ->assertRedirect(route('reviews.index'))
            ->assertSessionHas('success', 'Resurs boshqa kriteriyaga o‘tkazildi.');

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'criterion_id' => $targetCriterion->id,
            'status' => 'checking',
            'point' => 0,
            'reviewer_hemis_id' => null,
            'reason' => 'Kriteriya o‘zgartirildi. Inson tekshiruvi kutilmoqda.',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'type' => 'info',
            'message_type' => 'criterion_transferred',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message' => "Resurs “Manual test mezoni” (#{$sourceCriterion->id}) kriteriyasidan “Tegishli mezon” (#{$targetCriterion->id}) kriteriyasiga o‘tkazildi.",
        ]);
        $this->assertSame('uploads/proof.pdf', $datum->refresh()->storagePath());
    }

    public function test_criterion_transfer_rejects_unauthorized_and_invalid_destinations(): void
    {
        $reviewer = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $owner = User::factory()->create();
        $sourceCriterion = $this->createCriterion();
        $crossReportCriterion = $this->createCriterion();
        $this->assign($reviewer, $sourceCriterion, '1/'.$sourceCriterion->id);
        $datum = $this->createDatum($owner, $sourceCriterion);

        $this->patch(route('reviews.transfer-criterion', $datum), [
            'criterion_id' => $crossReportCriterion->id,
        ])->assertRedirect(route('login'));

        $this->actingAs($unauthorizedUser)
            ->patch(route('reviews.transfer-criterion', $datum), [
                'criterion_id' => $crossReportCriterion->id,
            ])
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.transfer-criterion', $datum), [
                'criterion_id' => $sourceCriterion->id,
            ])
            ->assertRedirect(route('reviews.show', $datum))
            ->assertSessionHasErrors('criterion_id');

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.transfer-criterion', $datum), [
                'criterion_id' => $crossReportCriterion->id,
            ])
            ->assertRedirect(route('reviews.show', $datum))
            ->assertSessionHasErrors('criterion_id');

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'criterion_id' => $sourceCriterion->id,
            'status' => 'received',
        ]);
        $this->assertDatabaseMissing('datum_histories', [
            'datum_id' => $datum->id,
            'message_type' => 'criterion_transferred',
        ]);
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
        $scoreOption = $this->createScoreOption($criterion, 'video_lesson', 'Videodars', 1.5);
        $this->createScoreOption($criterion, 'video_clip', 'Videorolik', 1);
        $this->createScoreOption($criterion, 'presentation', 'Taqdimot', 0.5);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Videodars')
            ->assertSee('Videorolik')
            ->assertSee('Taqdimot');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $scoreOption->id,
            ])
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 1.5,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'message_type' => 'manual_review_approved',
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'message' => 'Mas’ul tomonidan tasdiqlandi. Qoida: Videodars. Hisoblangan ball: 1.50.',
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
        $scoreOption = $this->createScoreOption($criterion, 'video_lesson', 'Videodars', 1.5);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum), [
                'score_option_id' => $scoreOption->id,
            ])
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

    public function test_manual_approval_rejects_missing_or_other_criterion_score_option(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $otherCriterion = $this->createCriterion();
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
        $otherOption = $this->createScoreOption($otherCriterion, 'other', 'Boshqa mezon varianti', 1);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum))
            ->assertSessionHasErrors('score_option_id');

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum), ['score_option_id' => $otherOption->id])
            ->assertSessionHasErrors('score_option_id');

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'received',
            'point' => 0,
        ]);
    }

    public function test_fixed_manual_rule_awards_one_raw_point_with_approve_click_only(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'hold_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update(['desc' => ['uz' => 'OAK diplomi asosida baholanadi.']]);
        $this->assign($reviewer, $criterion, '3/25');
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 3,
        ]);
        $scoreOption = $this->createScoreOption(
            $criterion,
            'dsc_diploma',
            'OAK tasdiqlagan DSc diplomi',
            1,
        );
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('OAK diplomi asosida baholanadi.')
            ->assertDontSee('name="score_option_id"', false);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertRedirect(route('reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 1,
        ]);
    }

    public function test_assigned_non_manual_criterion_requires_an_explicit_score_handler(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update(['checking' => 'department']);
        $this->assign($reviewer, $criterion, '1/6');
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 3,
        ]);
        $datum = $this->createDatum($owner, $criterion);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum))
            ->assertSessionHasErrors('datum');

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'received',
            'point' => 0,
        ]);
    }

    public function test_assigned_reviewer_enters_exact_point_for_ai_submission_left_for_review(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update(['checking' => 'ai']);
        $this->assignAiHumanReviewer($reviewer);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 3,
        ]);
        $datum = $this->createDatum($owner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Ball bilan tasdiqlash')
            ->assertSee('Ruxsat etilgan oraliq: 0–3.00 ball');

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum), ['point' => 3.5])
            ->assertSessionHasErrors('point');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), ['point' => 1.25])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 1.25,
            'reviewer_hemis_id' => null,
        ]);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $datum->id,
            'user_id' => $reviewer->id,
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_ai_human_review_uses_the_criterion_submission_maximum(): void
    {
        $reviewer = User::factory()->create();
        $owner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update([
            'checking' => 'ai',
            'ai_submission_max_point' => 5,
        ]);
        $this->assignAiHumanReviewer($reviewer);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 3,
        ]);
        $datum = $this->createDatum($owner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $datum))
            ->patch(route('reviews.approve', $datum), ['point' => 5.01])
            ->assertSessionHasErrors('point');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $datum), ['point' => 5])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertDatabaseHas('data', [
            'id' => $datum->id,
            'status' => 'accepted',
            'point' => 5,
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

    /** @param array<string, mixed> $attributes */
    private function createSiblingCriterion(
        Criterion $criterion,
        string $name,
        array $attributes = [],
    ): Criterion {
        return Criterion::query()->create(array_merge([
            'name' => ['uz' => $name],
            'parent_id' => $criterion->parent_id,
            'report_id' => $criterion->report_id,
            'formula_id' => $criterion->formula_id,
            'checking' => 'manual',
            'upload' => '1',
            'status' => '1',
        ], $attributes));
    }

    private function assign(User $reviewer, Criterion $criterion, string $code): CriterionReviewerAssignment
    {
        return CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->id,
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => $code,
        ]);
    }

    private function assignAiHumanReviewer(User $reviewer): AiHumanReviewAssignment
    {
        return AiHumanReviewAssignment::query()->create([
            'hemis_id' => $reviewer->hemis_id,
            'active_slot' => 1,
            'assigned_at' => now(),
        ]);
    }

    private function createScoreOption(
        Criterion $criterion,
        string $code,
        string $label,
        float $point,
    ): CriterionManualScoreOption {
        return CriterionManualScoreOption::query()->create([
            'criterion_id' => $criterion->id,
            'code' => $code,
            'label' => ['uz' => $label],
            'point' => $point,
            'sort_order' => 1,
            'active' => true,
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
