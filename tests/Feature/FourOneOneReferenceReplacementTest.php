<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiDatumEvaluation;
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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FourOneOneReferenceReplacementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_replace_a_detected_reference_once_while_regular_uploads_are_closed(): void
    {
        config()->set('kpi.resource_upload_deadline', '2026-08-15 23:59:59');
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', 'Asia/Tashkent'));
        Option::setResourceUploadsEnabled(false);
        Queue::fake();
        [$criterion, $year] = $this->createCriterion();
        $teacher = User::factory()->create();
        $rejectedDatum = $this->createRejectedReference($teacher, $criterion, $year);

        $this->actingAs($teacher)
            ->get(route('upload.show', $criterion))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('upload.details', $rejectedDatum))
            ->assertOk()
            ->assertSee(route('upload.four-one-one-reference.replace', $rejectedDatum));

        $this->actingAs($teacher)
            ->get(route('upload.four-one-one-reference.replace', $rejectedDatum))
            ->assertOk()
            ->assertSee('name="replacement_datum_id"', false)
            ->assertSee('maqolaning PDF/JPG faylini yoki gazeta, OAV yoxud ijtimoiy tarmoqdagi chiqishning hammaga ochiq')
            ->assertSee('Ma’lumotnoma qabul qilinmaydi.');

        $response = $this->actingAs($teacher)->post(route('upload.store', $criterion), [
            'replacement_datum_id' => $rejectedDatum->getKey(),
            'uploadResourceType' => 'url',
            'uploadResourceUrl' => 'https://example.com/actual-media-publication',
            'year' => $year->getKey(),
        ]);

        $newDatum = Datum::query()->whereKeyNot($rejectedDatum->getKey())->sole();
        $response->assertRedirect(route('upload.details', $newDatum));
        $this->assertSame('checking', $newDatum->status);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $rejectedDatum->getKey(),
            'message_type' => 'four_one_one_reference_replacement_submitted',
        ]);
        Queue::assertPushed(
            ProcessAiDatumEvaluation::class,
            fn (ProcessAiDatumEvaluation $job): bool => $job->datumId === $newDatum->getKey(),
        );

        $this->actingAs($teacher)
            ->get(route('upload.four-one-one-reference.replace', $rejectedDatum))
            ->assertForbidden();
        $this->actingAs($teacher)
            ->post(route('upload.store', $criterion), [
                'replacement_datum_id' => $rejectedDatum->getKey(),
                'uploadResourceType' => 'url',
                'uploadResourceUrl' => 'https://example.com/second-attempt',
                'year' => $year->getKey(),
            ])
            ->assertForbidden();
        $this->assertDatabaseCount('data', 2);
    }

    public function test_replacement_is_denied_without_the_special_recheck_and_reference_reason(): void
    {
        [$criterion, $year] = $this->createCriterion();
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $eligible = $this->createRejectedReference($teacher, $criterion, $year);
        $withoutMarker = $this->createCancelledDatum($teacher, $criterion, $year);
        $withoutMarker->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'error',
            'message' => 'Bu hujjat ma’lumotnoma.',
            'message_type' => 'ai_evaluation',
        ]);
        $notAReference = $this->createCancelledDatum($teacher, $criterion, $year);
        $notAReference->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'info',
            'message' => 'Qayta tekshiruvga yuborildi.',
            'message_type' => 'ai_four_one_one_reference_recheck_queued',
        ]);
        $notAReference->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'error',
            'message' => 'Yuklangan hujjat OAVdagi chiqishni tasdiqlamaydi.',
            'message_type' => 'ai_evaluation',
        ]);

        $this->assertFalse($teacher->can('replaceFourOneOneReference', $withoutMarker));
        $this->assertFalse($teacher->can('replaceFourOneOneReference', $notAReference));
        $this->assertFalse($otherTeacher->can('replaceFourOneOneReference', $eligible));

        $criterion->update(['code' => '4.1.2']);
        $this->assertFalse($teacher->can('replaceFourOneOneReference', $eligible));
        $criterion->update(['code' => '4.1.1']);
        $teacher->update(['upload_blocked_at' => now()]);
        $this->assertFalse($teacher->can('replaceFourOneOneReference', $eligible));
    }

    public function test_later_human_decision_and_reference_negation_do_not_open_replacement(): void
    {
        [$criterion, $year] = $this->createCriterion();
        $teacher = User::factory()->create();
        $laterHumanDecision = $this->createRejectedReference($teacher, $criterion, $year);
        $laterHumanDecision->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'success',
            'message' => 'Inson tomonidan yakuniy qaror qabul qilindi.',
            'message_type' => 'human_override_approved',
        ]);
        $negatedReference = $this->createCancelledDatum($teacher, $criterion, $year);
        $negatedReference->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'info',
            'message' => 'Qayta tekshiruvga yuborildi.',
            'message_type' => 'ai_four_one_one_reference_recheck_queued',
        ]);
        $negatedReference->histories()->create([
            'user_id' => $teacher->getKey(),
            'type' => 'error',
            'message' => 'Bu ma’lumotnoma emas, lekin chiqish tasdiqlanmadi.',
            'message_type' => 'ai_evaluation',
        ]);

        $this->assertFalse($teacher->can('replaceFourOneOneReference', $laterHumanDecision));
        $this->assertFalse($teacher->can('replaceFourOneOneReference', $negatedReference));
    }

    /** @return array{Criterion, Year} */
    private function createCriterion(): array
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
            'code' => '4.1.1',
            'name' => ['uz' => 'OAV chiqishlari'],
            'desc' => ['uz' => 'Test tavsifi'],
            'report_id' => $report->getKey(),
            'upload' => '1',
            'status' => '1',
            'res_type' => 'all',
            'checking' => 'ai',
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

    private function createRejectedReference(User $user, Criterion $criterion, Year $year): Datum
    {
        $datum = $this->createCancelledDatum($user, $criterion, $year);
        $datum->histories()->create([
            'user_id' => $user->getKey(),
            'type' => 'info',
            'message' => 'Ma’lumotnoma qabul etilmaydi. Resurs qayta AI tekshiruviga yuborildi.',
            'message_type' => 'ai_four_one_one_reference_recheck_queued',
        ]);
        $datum->histories()->create([
            'user_id' => $user->getKey(),
            'type' => 'error',
            'message' => 'Yuklangan resurs ma’lumotnoma bo‘lgani sababli qabul qilinmaydi.',
            'message_type' => 'ai_evaluation',
        ]);

        return $datum;
    }

    private function createCancelledDatum(User $user, Criterion $criterion, Year $year): Datum
    {
        return Datum::query()->create([
            'name' => 'Eski ma’lumotnoma.pdf',
            'material' => ['type' => 'url', 'link' => 'https://example.com/reference/'.fake()->uuid()],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'status' => 'cancelled',
            'point' => 0,
            'reason' => 'Ma’lumotnoma qabul etilmaydi.',
        ]);
    }
}
