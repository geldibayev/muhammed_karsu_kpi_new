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

    public function test_ai_human_reviews_can_be_filtered_by_criteria_with_pending_resources(): void
    {
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $owner = User::factory()->create();
        $firstCriterion = $this->createCriterion();
        $firstCriterion->update([
            'code' => '4.1.1',
            'name' => ['uz' => 'Birinchi AI kriteriya'],
            'checking' => 'ai',
        ]);
        $secondCriterion = $this->createSiblingCriterion($firstCriterion, 'Ikkinchi AI kriteriya', [
            'code' => '4.1.2',
            'checking' => 'ai',
        ]);
        $emptyCriterion = $this->createSiblingCriterion($firstCriterion, 'Resurssiz AI kriteriya', [
            'code' => '4.1.3',
            'checking' => 'ai',
        ]);
        $manualCriterion = $this->createSiblingCriterion($firstCriterion, 'Manual kriteriya');
        $this->assignAiHumanReviewer($reviewer);

        $firstResource = $this->createDatum($owner, $firstCriterion, [
            'name' => 'Birinchi filtrlanadigan resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $secondResource = $this->createDatum($owner, $secondCriterion, [
            'name' => 'Ikkinchi filtrlanadigan resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $this->createDatum($owner, $emptyCriterion, [
            'name' => 'Boshqa tekshiruvchidagi resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $otherReviewer->hemis_id,
        ]);
        $this->createDatum($owner, $manualCriterion, [
            'name' => 'Manual tekshiruvdagi resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee('Kriteriya bo‘yicha filtr')
            ->assertSee('Birinchi AI kriteriya')
            ->assertSee('Ikkinchi AI kriteriya')
            ->assertDontSee('Resurssiz AI kriteriya')
            ->assertDontSee('Manual kriteriya')
            ->assertSee($firstResource->name)
            ->assertSee($secondResource->name);

        $this->actingAs($reviewer)
            ->get(route('ai-human-reviews.index', ['criterion' => $firstCriterion->id]))
            ->assertOk()
            ->assertSee('Birinchi AI kriteriya')
            ->assertSee('Ikkinchi AI kriteriya')
            ->assertSee($firstResource->name)
            ->assertDontSee($secondResource->name);

        $this->actingAs($reviewer)
            ->from(route('ai-human-reviews.index'))
            ->get(route('ai-human-reviews.index', ['criterion' => $emptyCriterion->id]))
            ->assertRedirect(route('ai-human-reviews.index'))
            ->assertSessionHasErrors('criterion');
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
        $this->artisan('kpi:ai:assign-human-reviews', [
            '--reassign' => true,
        ])
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

    public function test_assignment_command_reassigns_existing_criterion_specific_ai_human_reviews(): void
    {
        config()->set('kpi.ai_human_review_criterion_reviewers', [
            '2.1.6' => 3462611061,
        ]);
        $criterionReviewer = User::factory()->create(['hemis_id' => 3462611061]);
        $globalReviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create();
        $criterion = $this->createCriterion();
        $criterion->update([
            'code' => '2.1.6',
            'checking' => 'ai',
        ]);
        $this->assertSame(3462611061, AiHumanReviewAssignment::reviewerHemisIdFor($criterion));
        $this->assignAiHumanReviewer($globalReviewer);
        $datum = $this->createDatum($owner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $globalReviewer->hemis_id,
        ]);
        $datum->histories()->create([
            'user_id' => $owner->id,
            'type' => 'warning',
            'message' => 'Inson tekshiruvi kerak.',
            'message_type' => 'ai_evaluation',
        ]);

        $this->artisan('kpi:ai:assign-human-reviews', [
            '--criterion' => '2.1.6',
            '--reassign' => true,
        ])
            ->expectsOutput('AI inson tekshiruvi uchun biriktirildi: 1')
            ->assertSuccessful();

        $this->assertSame($criterionReviewer->hemis_id, $datum->fresh()->reviewer_hemis_id);
        $this->actingAs($criterionReviewer)
            ->get(route('ai-human-reviews.index'))
            ->assertOk()
            ->assertSee($datum->name);
    }

    public function test_assignment_command_routes_educational_literature_reviews_to_configured_reviewer(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3862011037]);
        $oldReviewer = User::factory()->create();
        $owner = User::factory()->create();
        $baseCriterion = $this->createCriterion();
        $data = collect(['1.2', '1.3', '1.4', '1.10'])->mapWithKeys(function (string $code) use (
            $baseCriterion,
            $oldReviewer,
            $owner,
        ): array {
            $criterion = $this->createSiblingCriterion($baseCriterion, $code.' kriteriya', [
                'code' => $code,
                'checking' => 'ai',
            ]);
            $datum = $this->createDatum($owner, $criterion, [
                'name' => $code.' inson tekshiruvi',
                'status' => 'checking',
                'reviewer_hemis_id' => $oldReviewer->hemis_id,
            ]);
            $datum->histories()->create([
                'user_id' => $owner->id,
                'type' => 'warning',
                'message' => 'Inson tekshiruvi kerak.',
                'message_type' => 'ai_evaluation',
            ]);

            return [$code => $datum];
        });

        foreach ($data as $code => $datum) {
            $this->artisan('kpi:ai:assign-human-reviews', [
                '--criterion' => $code,
                '--reassign' => true,
            ])->assertSuccessful();

            $this->assertSame($reviewer->hemis_id, $datum->fresh()->reviewer_hemis_id);
        }
    }

    public function test_assignment_command_routes_scientific_ai_human_reviews_to_one_reviewer(): void
    {
        $criterionCodes = ['3.1.1', '3.1.2', '3.1.3', '3.1.8', '3.1.15'];
        config()->set(
            'kpi.ai_human_review_criterion_reviewers',
            array_fill_keys($criterionCodes, 3462011207),
        );
        $criterionReviewer = User::factory()->create(['hemis_id' => 3462011207]);
        $globalReviewer = User::factory()->create(['hemis_id' => 3172011004]);
        $owner = User::factory()->create();
        $baseCriterion = $this->createCriterion();
        $this->assignAiHumanReviewer($globalReviewer);
        $data = collect($criterionCodes)->mapWithKeys(function (string $code) use (
            $baseCriterion,
            $globalReviewer,
            $owner,
        ): array {
            $criterion = $this->createSiblingCriterion($baseCriterion, $code.' kriteriya', [
                'code' => $code,
                'checking' => 'ai',
            ]);
            $datum = $this->createDatum($owner, $criterion, [
                'name' => $code.' inson tekshiruvi',
                'status' => 'checking',
                'reviewer_hemis_id' => $globalReviewer->hemis_id,
            ]);
            $datum->histories()->create([
                'user_id' => $owner->id,
                'type' => 'warning',
                'message' => 'Inson tekshiruvi kerak.',
                'message_type' => 'ai_evaluation',
            ]);

            return [$code => $datum];
        });

        foreach ($criterionCodes as $code) {
            $this->artisan('kpi:ai:assign-human-reviews', [
                '--criterion' => $code,
                '--reassign' => true,
            ])->assertSuccessful();
        }

        foreach ($data as $datum) {
            $this->assertSame($criterionReviewer->hemis_id, $datum->fresh()->reviewer_hemis_id);
        }
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

    public function test_criterion_2_1_1_ai_human_approval_uses_degree_score_without_manual_point(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462611061]);
        $withDegreeOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegreeOwner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update([
            'code' => '2.1.1',
            'checking' => 'ai',
        ]);
        $this->assignAiHumanReviewer($reviewer);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 1,
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 2,
        ]);
        $withDegreeDatum = $this->createDatum($withDegreeOwner, $criterion, [
            'name' => 'Ilmiy darajali resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $withoutDegreeDatum = $this->createDatum($withoutDegreeOwner, $criterion, [
            'name' => 'Ilmiy darajasiz resurs',
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $withDegreeDatum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertDontSee('name="point"', false)
            ->assertDontSee('Ball bilan tasdiqlash');

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $withDegreeDatum))
            ->patch(route('reviews.approve', $withDegreeDatum), ['point' => 1])
            ->assertSessionHasErrors('point');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $withDegreeDatum))
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $withoutDegreeDatum))
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertSame(1.0, $withDegreeDatum->fresh()->point);
        $this->assertSame(2.0, $withoutDegreeDatum->fresh()->point);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $withDegreeDatum->id,
            'user_id' => $reviewer->id,
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_criterion_2_1_6_human_review_calculates_point_from_selected_university_tier(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462611061]);
        $standardOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $specialOwner = User::factory()->create(['degree' => 'foreign_lang']);
        $criterion = $this->createCriterion();
        $criterion->update(['code' => '2.1.6', 'checking' => 'ai']);

        foreach (['hold_degrees' => 3, 'foreign_lang' => 4] as $evaluation => $score) {
            Evaluation::query()->create([
                'code' => $evaluation,
                'name' => ['uz' => $evaluation],
                'status' => '1',
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => $score,
            ]);
        }

        $standardDatum = $this->createDatum($standardOwner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $specialDatum = $this->createDatum($specialOwner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $standardDatum))
            ->assertOk()
            ->assertSee('Universitet Top darajasi bilan tasdiqlash')
            ->assertSee('name="university_tier"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $standardDatum))
            ->patch(route('reviews.approve', $standardDatum))
            ->assertSessionHasErrors('university_tier');
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $standardDatum))
            ->patch(route('reviews.approve', $standardDatum), [
                'university_tier' => 'top_300',
                'point' => 3,
            ])
            ->assertSessionHasErrors('point');
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $standardDatum))
            ->patch(route('reviews.approve', $standardDatum), ['university_tier' => 'top_50'])
            ->assertSessionHasErrors('university_tier');

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $standardDatum), ['university_tier' => 'top_300'])
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $specialDatum), ['university_tier' => 'top_300'])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertSame(2.5, $standardDatum->fresh()->point);
        $this->assertSame('top_300', $standardDatum->fresh()->university_tier);
        $this->assertSame(3.5, $specialDatum->fresh()->point);
        $this->assertSame('top_300', $specialDatum->fresh()->university_tier);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $standardDatum->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_criterion_3_1_15_only_approves_scientific_degree_resources_for_two_points(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011207]);
        $withDegreeOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegreeOwner = User::factory()->create(['degree' => 'no_degrees']);
        $criterion = $this->createCriterion();
        $criterion->update(['code' => '3.1.15', 'checking' => 'ai']);

        foreach (['hold_degrees' => 'Ilmiy darajali', 'no_degrees' => 'Ilmiy darajasiz'] as $code => $name) {
            Evaluation::query()->firstOrCreate(
                ['code' => $code],
                ['name' => ['uz' => $name], 'status' => '1'],
            );
        }
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 2,
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '0',
            'score' => 0,
        ]);

        $withDegreeDatum = $this->createDatum($withDegreeOwner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $withoutDegreeDatum = $this->createDatum($withoutDegreeOwner, $criterion, [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $withDegreeDatum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertDontSee('name="point"', false);
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $withDegreeDatum))
            ->patch(route('reviews.approve', $withDegreeDatum), ['point' => 99])
            ->assertSessionHasErrors('point');
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $withDegreeDatum))
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $withoutDegreeDatum))
            ->patch(route('reviews.approve', $withoutDegreeDatum))
            ->assertSessionHasErrors('datum');

        $this->assertSame(2.0, $withDegreeDatum->fresh()->point);
        $this->assertSame('accepted', $withDegreeDatum->fresh()->status);
        $this->assertSame(0.0, $withoutDegreeDatum->fresh()->point);
        $this->assertSame('checking', $withoutDegreeDatum->fresh()->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $withDegreeDatum->getKey(),
            'user_id' => $reviewer->getKey(),
            'message_type' => 'manual_review_approved',
        ]);
    }

    public function test_educational_literature_human_approval_uses_the_correct_server_side_rules(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3862011037]);
        $withDegreeOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegreeOwner = User::factory()->create(['degree' => 'no_degrees']);
        $baseCriterion = $this->createCriterion();
        foreach (['hold_degrees' => 'Ilmiy darajali', 'no_degrees' => 'Ilmiy darajasiz'] as $code => $name) {
            Evaluation::query()->firstOrCreate(
                ['code' => $code],
                ['name' => ['uz' => $name], 'status' => '1'],
            );
        }
        $criteria = collect(['1.2', '1.3', '1.4'])->mapWithKeys(function (string $code) use ($baseCriterion): array {
            $criterion = $this->createSiblingCriterion($baseCriterion, $code.' AI kriteriya', [
                'code' => $code,
                'checking' => 'ai',
                'file_limit' => $code === '1.4' ? 1 : 0,
            ]);

            $scores = $code === '1.2'
                ? ['hold_degrees' => 6, 'no_degrees' => 5]
                : ['hold_degrees' => 5, 'no_degrees' => 4];

            foreach ($scores as $evaluation => $score) {
                CriterionEvaluation::query()->create([
                    'criterion_id' => $criterion->id,
                    'evaluation' => $evaluation,
                    'has' => '1',
                    'score' => $score,
                ]);
            }

            return [$code => $criterion];
        });

        $criterionOneTwoDatum = $this->createDatum($withDegreeOwner, $criteria->get('1.2'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $criterionOneThreeDatum = $this->createDatum($withoutDegreeOwner, $criteria->get('1.3'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $criterionOneFourWithDegreeDatum = $this->createDatum($withDegreeOwner, $criteria->get('1.4'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $criterionOneFourWithoutDegreeDatum = $this->createDatum($withoutDegreeOwner, $criteria->get('1.4'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $criterionOneTwoDatum))
            ->assertOk()
            ->assertSee('Sahifa va mualliflar bilan tasdiqlash')
            ->assertDontSee('name="point"', false)
            ->assertSee('name="author_count"', false)
            ->assertSee('name="page_count"', false);

        $this->actingAs($reviewer)
            ->from(route('reviews.show', $criterionOneTwoDatum))
            ->patch(route('reviews.approve', $criterionOneTwoDatum))
            ->assertSessionHasErrors(['page_count', 'author_count']);

        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $criterionOneTwoDatum), [
                'page_count' => 160,
                'author_count' => 2,
            ])
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $criterionOneThreeDatum), [
                'page_count' => 160,
                'author_count' => 2,
            ])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $criterionOneFourWithDegreeDatum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertDontSee('name="point"', false)
            ->assertDontSee('name="author_count"', false)
            ->assertDontSee('name="page_count"', false);
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $criterionOneFourWithDegreeDatum))
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $criterionOneFourWithoutDegreeDatum))
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->assertSame(2.0, $criterionOneTwoDatum->fresh()->point);
        $this->assertSame(1.5, $criterionOneThreeDatum->fresh()->point);
        $this->assertSame(5.0, $criterionOneFourWithDegreeDatum->fresh()->point);
        $this->assertSame(4.0, $criterionOneFourWithoutDegreeDatum->fresh()->point);
    }

    public function test_criterion_1_10_human_approval_uses_the_evaluation_category_score(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3862011037]);
        $baseCriterion = $this->createCriterion();
        $criterion = $this->createSiblingCriterion($baseCriterion, '1.10 AI kriteriya', [
            'code' => '1.10',
            'checking' => 'ai',
            'file_limit' => 1,
        ]);
        $scores = [
            'hold_degrees' => 2,
            'no_degrees' => 2,
            'foreign_lang' => 3,
            'physical' => 4,
        ];
        $data = collect($scores)->mapWithKeys(function (int $score, string $evaluation) use (
            $criterion,
            $reviewer,
        ): array {
            Evaluation::query()->firstOrCreate(
                ['code' => $evaluation],
                ['name' => ['uz' => $evaluation], 'status' => '1'],
            );
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $evaluation,
                'has' => '1',
                'score' => $score,
            ]);
            $owner = User::factory()->create(['degree' => $evaluation]);

            return [$evaluation => $this->createDatum($owner, $criterion, [
                'status' => 'checking',
                'reviewer_hemis_id' => $reviewer->hemis_id,
            ])];
        });

        $firstDatum = $data->first();
        $this->actingAs($reviewer)
            ->get(route('reviews.show', $firstDatum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertDontSee('name="point"', false)
            ->assertDontSee('name="author_count"', false)
            ->assertDontSee('name="page_count"', false);
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $firstDatum))
            ->patch(route('reviews.approve', $firstDatum), ['point' => 99])
            ->assertSessionHasErrors('point');

        foreach ($data as $evaluation => $datum) {
            $this->actingAs($reviewer)
                ->patch(route('reviews.approve', $datum))
                ->assertRedirect(route('ai-human-reviews.index'));

            $this->assertSame('accepted', $datum->fresh()->status);
            $this->assertSame((float) $scores[$evaluation], $datum->fresh()->point);
            $this->assertDatabaseHas('datum_histories', [
                'datum_id' => $datum->getKey(),
                'user_id' => $reviewer->getKey(),
                'message_type' => 'manual_review_approved',
            ]);
        }
    }

    public function test_scientific_publication_human_reviews_use_server_side_scoring_rules(): void
    {
        $reviewer = User::factory()->create(['hemis_id' => 3462011207]);
        $withDegreeOwner = User::factory()->create(['degree' => 'hold_degrees']);
        $withoutDegreeOwner = User::factory()->create(['degree' => 'no_degrees']);
        $baseCriterion = $this->createCriterion();
        $baseCriterion->formula()->update(['code' => Formula::Maximum]);
        Evaluation::query()->create([
            'code' => 'hold_degrees',
            'name' => ['uz' => 'Ilmiy darajali'],
            'status' => '1',
        ]);
        Evaluation::query()->create([
            'code' => 'no_degrees',
            'name' => ['uz' => 'Ilmiy darajasiz'],
            'status' => '1',
        ]);
        $criteria = collect([
            '3.1.2' => [2, 3],
            '3.1.3' => [5, 5],
            '3.1.8' => [3, 4],
        ])->mapWithKeys(function (array $scores, string $code) use ($baseCriterion): array {
            $criterion = $this->createSiblingCriterion($baseCriterion, $code.' AI kriteriya', [
                'code' => $code,
                'checking' => 'ai',
            ]);

            foreach (array_combine(['hold_degrees', 'no_degrees'], $scores) as $evaluation => $score) {
                CriterionEvaluation::query()->create([
                    'criterion_id' => $criterion->id,
                    'evaluation' => $evaluation,
                    'has' => '1',
                    'score' => $score,
                ]);
            }

            return [$code => $criterion];
        });
        $this->assignAiHumanReviewer($reviewer);

        $impactDatum = $this->createDatum($withoutDegreeOwner, $criteria->get('3.1.2'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $tierDatum = $this->createDatum($withDegreeOwner, $criteria->get('3.1.3'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $patentWithDegreeDatum = $this->createDatum($withDegreeOwner, $criteria->get('3.1.8'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);
        $patentWithoutDegreeDatum = $this->createDatum($withoutDegreeOwner, $criteria->get('3.1.8'), [
            'status' => 'checking',
            'reviewer_hemis_id' => $reviewer->hemis_id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $impactDatum))
            ->assertOk()
            ->assertSee('Impakt faktor bilan tasdiqlash')
            ->assertSee('name="impact_factor"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($reviewer)
            ->from(route('reviews.show', $impactDatum))
            ->patch(route('reviews.approve', $impactDatum), ['impact_factor' => 1.5])
            ->assertSessionHasErrors('impact_factor');
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $impactDatum), ['impact_factor' => 2])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $tierDatum))
            ->assertOk()
            ->assertSee('Kvartil bilan tasdiqlash')
            ->assertSee('name="publication_tier"', false);
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $tierDatum), ['publication_tier' => 'conference'])
            ->assertRedirect(route('ai-human-reviews.index'));

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $patentWithDegreeDatum))
            ->assertOk()
            ->assertSee('Mualliflar soni bilan tasdiqlash')
            ->assertSee('name="author_count"', false)
            ->assertDontSee('name="point"', false);
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $patentWithDegreeDatum), ['author_count' => 2])
            ->assertRedirect(route('ai-human-reviews.index'));
        $this->actingAs($reviewer)
            ->patch(route('reviews.approve', $patentWithoutDegreeDatum), ['author_count' => 4])
            ->assertRedirect(route('ai-human-reviews.index'));

        $impactDatum->refresh();
        $tierDatum->refresh();
        $patentWithDegreeDatum->refresh();
        $patentWithoutDegreeDatum->refresh();

        $this->assertSame(0.6, $impactDatum->point);
        $this->assertSame(2, $impactDatum->impact_factor);
        $this->assertSame(2.5, $tierDatum->point);
        $this->assertSame('conference', $tierDatum->publication_tier);
        $this->assertSame(1.5, $patentWithDegreeDatum->point);
        $this->assertSame(1.0, $patentWithoutDegreeDatum->point);
        $this->assertSame(2, $patentWithDegreeDatum->author_count);
        $this->assertSame(4, $patentWithoutDegreeDatum->author_count);
        $this->assertSame(0.6, (float) Point::query()
            ->where('user_id', $withoutDegreeOwner->id)
            ->where('criterion_id', $criteria->get('3.1.2')->id)
            ->value('point'));
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
