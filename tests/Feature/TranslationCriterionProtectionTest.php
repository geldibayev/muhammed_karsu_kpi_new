<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Models\Year;
use App\Services\AiSubmissionEvaluator;
use App\Support\TranslatedEducationalLiteratureCriterionRule;
use Gemini\Data\GenerationConfig;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use UnexpectedValueException;

class TranslationCriterionProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_cannot_upload_a_file_that_already_scored_in_criterion_one_two(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user, $report, $year, $sourceCriterion, $translationCriterion] = $this->context();
        $contents = 'same textbook bytes';
        $source = $this->acceptedFile($user, $sourceCriterion, $year, $contents, 2.5);

        $this->actingAs($user)
            ->post(route('upload.store', $translationCriterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->createWithContent('translation.pdf', $contents),
                'year' => $year->getKey(),
            ])
            ->assertSessionHasErrors([
                'uploadResourceFile' => "Ushbu fayl 1.2 mezonida #{$source->getKey()} resurs sifatida qabul qilingan va ball olgan. 1.4 mezoniga faqat boshqa tildan qilingan tarjimani tasdiqlovchi alohida resurs yuklang.",
            ]);

        $this->assertDatabaseCount('data', 1);
        $this->assertCount(0, Storage::disk('local')->allFiles('uploads/kpi_resources'));
    }

    public function test_same_file_does_not_block_a_different_user(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$owner, $report, $year, $sourceCriterion, $translationCriterion] = $this->context();
        $otherUser = User::factory()->create(['degree' => $owner->degree]);
        $contents = 'shared coauthor textbook bytes';
        $this->acceptedFile($owner, $sourceCriterion, $year, $contents, 2);

        $this->actingAs($otherUser)
            ->post(route('upload.store', $translationCriterion), [
                'uploadResourceType' => 'file',
                'uploadResourceFile' => UploadedFile::fake()->createWithContent('translated.pdf', $contents),
                'year' => $year->getKey(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('data', 2);
    }

    public function test_audit_command_cancels_existing_one_four_duplicate_with_reason_idempotently(): void
    {
        Storage::fake('local');
        [$user, $report, $year, $sourceCriterion, $translationCriterion] = $this->context();
        $contents = 'legacy duplicate textbook bytes';
        $source = $this->acceptedFile($user, $sourceCriterion, $year, $contents, 2);
        $duplicate = $this->acceptedFile($user, $translationCriterion, $year, $contents, 4);

        $this->artisan('kpi:translations:audit-duplicates', ['--report' => $report->getKey()])
            ->expectsOutputToContain('Bekor qilinishi kerak bo‘lgan 1.4 resurslari: 1')
            ->expectsOutputToContain('Dry-run yakunlandi')
            ->assertSuccessful();

        $this->assertSame('accepted', $duplicate->fresh()->status);

        $this->artisan('kpi:translations:audit-duplicates', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $duplicate->refresh();
        $this->assertSame('cancelled', $duplicate->status);
        $this->assertSame(0.0, $duplicate->point);
        $this->assertSame($source->getKey(), $duplicate->duplicate_of_id);
        $this->assertStringContainsString('1.2 mezonida', (string) $duplicate->reason);
        $this->assertStringContainsString("#{$source->getKey()}", (string) $duplicate->reason);
        $this->assertDatabaseHas('datum_histories', [
            'datum_id' => $duplicate->getKey(),
            'message_type' => 'translation_duplicate_cancelled',
        ]);

        $this->artisan('kpi:translations:audit-duplicates', [
            '--report' => $report->getKey(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1, $duplicate->histories()
            ->where('message_type', 'translation_duplicate_cancelled')
            ->count());
    }

    public function test_ai_cannot_accept_one_four_without_verified_distinct_translation_languages(): void
    {
        $payload = [
            'status' => 'accepted',
            'point' => 1,
            'author_count' => 1,
            'resource_date' => '2026',
            'is_translation' => false,
            'source_language' => 'O‘zbek',
            'target_language' => 'O‘zbek',
            'reason' => 'Oddiy darslik.',
        ];

        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload($payload, 5, requiresTranslationEvidence: true);
    }

    public function test_ai_accepts_one_four_when_translation_and_distinct_languages_are_verified(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 1,
            'author_count' => 2,
            'resource_date' => '2026',
            'is_translation' => true,
            'source_language' => 'Ingliz',
            'target_language' => 'O‘zbek',
            'reason' => 'Ingliz tilidagi asarning o‘zbekcha tarjimasi titul varaqda tasdiqlangan.',
        ], 5, requiresTranslationEvidence: true);

        $this->assertSame('accepted', $result->status);
        $this->assertStringContainsString(
            'source_language va target_language',
            TranslatedEducationalLiteratureCriterionRule::aiInstruction(),
        );
    }

    public function test_one_four_ai_request_requires_translation_evidence_fields(): void
    {
        Storage::fake('local');
        [$user, $report, $year, $sourceCriterion, $translationCriterion] = $this->context();
        Storage::disk('local')->put('translation.pdf', "%PDF-1.4\ntranslated test document");
        $datum = Datum::query()->create([
            'name' => 'translation.pdf',
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'translation.pdf',
                'mime' => 'application/pdf',
            ],
            'user_id' => $user->getKey(),
            'criterion_id' => $translationCriterion->getKey(),
            'year_id' => $year->getKey(),
            'status' => 'checking',
            'point' => 0,
        ]);

        Gemini::fake([
            GenerateContentResponse::fake([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'status' => 'accepted',
                                'point' => 1,
                                'author_count' => 1,
                                'resource_date' => '2026-01-10',
                                'is_translation' => true,
                                'source_language' => 'Ingliz',
                                'target_language' => 'O‘zbek',
                                'reason' => 'Tarjima titul varaqda tasdiqlangan.',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = $this->app->make(AiSubmissionEvaluator::class)->evaluate($datum);

        $this->assertSame('accepted', $result->status);
        Gemini::assertSent(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $contentParts = $parameters[0] ?? null;
                $prompt = is_array($contentParts) ? ($contentParts[0] ?? null) : null;

                return $method === 'generateContent'
                    && is_string($prompt)
                    && str_contains($prompt, 'TARJIMA HOLATINI MAJBURIY TEKSHIRISH')
                    && str_contains($prompt, 'is_translation true')
                    && str_contains($prompt, 'source_language')
                    && str_contains($prompt, 'target_language');
            },
        );
        Gemini::assertFunctionCalled(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $config = $parameters[0] ?? null;
                $schema = $config instanceof GenerationConfig ? $config->responseSchema?->toArray() : null;

                return $method === 'withGenerationConfig'
                    && data_get($schema, 'properties.is_translation.type') === 'BOOLEAN'
                    && data_get($schema, 'properties.source_language.type') === 'STRING'
                    && data_get($schema, 'properties.target_language.type') === 'STRING';
            },
        );
    }

    /** @return array{User, Report, Year, Criterion, Criterion} */
    private function context(): array
    {
        $user = User::factory()->create(['degree' => 'no_degrees']);
        Evaluation::query()->firstOrCreate(
            ['code' => 'no_degrees'],
            ['name' => ['uz' => 'Ilmiy darajasiz'], 'status' => '1'],
        );
        $report = Report::query()->create([
            'name' => ['uz' => 'Tarjima auditi'],
            'status' => '1',
        ]);
        $year = Year::query()->create([
            'id' => 2026,
            'name' => '2026',
            'status' => '1',
        ]);
        $formula = Formula::query()->create([
            'code' => Formula::Maximum,
            'name' => ['uz' => 'Maksimal'],
            'status' => '1',
        ]);
        $parent = Criterion::query()->create([
            'name' => ['uz' => 'O‘quv-uslubiy ishlar'],
            'report_id' => $report->getKey(),
            'status' => '1',
        ]);
        $sourceCriterion = $this->criterion($report, $year, $formula, $parent, '1.2');
        $translationCriterion = $this->criterion($report, $year, $formula, $parent, '1.4');

        return [$user, $report, $year, $sourceCriterion, $translationCriterion];
    }

    private function criterion(
        Report $report,
        Year $year,
        Formula $formula,
        Criterion $parent,
        string $code,
    ): Criterion {
        $criterion = Criterion::query()->create([
            'code' => $code,
            'name' => ['uz' => $code.' mezoni'],
            'desc' => ['uz' => 'Test tavsifi'],
            'parent_id' => $parent->getKey(),
            'report_id' => $report->getKey(),
            'formula_id' => $formula->getKey(),
            'upload' => '1',
            'file_limit' => 0,
            'status' => '1',
            'res_type' => 'file',
            'checking' => 'ai',
            'template' => '0',
            'ai_prompt' => 'Hujjatni tekshiring.',
            'ai_model' => 'gemini-test',
        ]);
        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->getKey(),
            'evaluation' => 'no_degrees',
            'has' => '1',
            'score' => 5,
        ]);
        DB::table('criterion_years')->insert([
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $criterion;
    }

    private function acceptedFile(
        User $user,
        Criterion $criterion,
        Year $year,
        string $contents,
        float $point,
    ): Datum {
        return Datum::query()->create([
            'name' => 'book.pdf',
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'legacy/'.hash('sha256', $contents).'.pdf',
                'original_name' => 'book.pdf',
                'extension' => 'pdf',
                'mime' => 'application/pdf',
                'sha256' => hash('sha256', $contents),
            ],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'year_id' => $year->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }
}
