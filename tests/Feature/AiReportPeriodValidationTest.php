<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Datum;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Observance;
use App\Models\Report;
use App\Models\User;
use App\Services\AiAuthorPointDistributor;
use App\Services\AiResourceDatePolicy;
use App\Services\AiSubmissionEvaluator;
use App\Services\GeminiFileMimeTypeResolver;
use App\Services\GeminiUrlContextGateway;
use App\Services\IndustryFundingScoreCalculator;
use App\Services\InternationalCooperationScoreValidator;
use App\Services\OakArticleScoreCalculator;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use App\Support\OakArticleCriterionRule;
use App\Support\ScopusCriterionRule;
use Gemini\Data\GenerationConfig;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiReportPeriodValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('publicationDates')]
    public function test_server_enforces_inclusive_report_period(
        string $resourceDate,
        string $expectedStatus,
        float $expectedPoint,
        ?string $expectedReason,
    ): void {
        $result = $this->evaluateResource($resourceDate);

        $this->assertSame($expectedStatus, $result->status);
        $this->assertSame($expectedPoint, $result->point);

        if ($expectedReason !== null) {
            $this->assertStringContainsString($expectedReason, $result->reason);
        }

    }

    public function test_scopus_point_is_not_divided_between_authors(): void
    {
        $result = $this->evaluateResource('2026-08-31');

        $this->assertSame('accepted', $result->status);
        $this->assertSame(20.0, $result->point);
        $this->assertSame('q1', $result->publicationTier);
    }

    public function test_printed_literature_point_is_calculated_on_server_from_pages_and_authors(): void
    {
        $result = $this->evaluateResource('2025', criterionCode: '1.2');

        $this->assertSame('accepted', $result->status);
        $this->assertSame(1.0, $result->point);
        $this->assertSame(160, $result->pageCount);
        $this->assertSame(4, $result->authorCount);
        $this->assertStringContainsString('160 sahifa / 16 × 0.4 / 4 muallif', $result->reason);
        Gemini::assertFunctionCalled(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $generationConfig = $parameters[0] ?? null;
                $schema = $generationConfig instanceof GenerationConfig
                    ? $generationConfig->responseSchema?->toArray()
                    : null;

                return $method === 'withGenerationConfig'
                    && data_get($schema, 'properties.page_count.type') === 'INTEGER'
                    && in_array('page_count', data_get($schema, 'required', []), true)
                    && in_array('author_count', data_get($schema, 'required', []), true);
            },
        );
    }

    #[DataProvider('oakArticleYearsAndIssues')]
    public function test_oak_articles_use_publication_year_and_issue(
        ?string $resourceDate,
        int $publicationIssue,
        string $expectedStatus,
    ): void {
        config()->set('kpi.report_period_start', '2025-09-01');
        config()->set('kpi.report_period_end', '2026-08-31');
        $datum = new Datum;
        $datum->setRelation('criterion', new Criterion(['code' => OakArticleCriterionRule::CODE]));
        $result = new AiEvaluationResult(
            status: 'accepted',
            point: 0.5,
            reason: 'OAK maqolasi tasdiqlandi.',
            authorCount: 1,
            resourceDate: $resourceDate,
            publicationIssue: $publicationIssue,
        );

        $enforced = (new AiResourceDatePolicy)->enforce($datum, $result);

        $this->assertSame($expectedStatus, $enforced->status);
    }

    public function test_oak_year_only_result_and_issue_are_required_in_ai_schema(): void
    {
        $result = $this->evaluateResource(
            '2025',
            criterionCode: OakArticleCriterionRule::CODE,
            aiReason: 'OAK jurnalining 3-soni tasdiqlandi.',
            publicationIssue: 3,
        );

        $this->assertSame('accepted', $result->status);
        $this->assertSame(3, $result->publicationIssue);
        Gemini::assertFunctionCalled(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $generationConfig = $parameters[0] ?? null;
                $schema = $generationConfig instanceof GenerationConfig
                    ? $generationConfig->responseSchema?->toArray()
                    : null;

                return $method === 'withGenerationConfig'
                    && data_get($schema, 'properties.publication_issue.type') === 'INTEGER'
                    && in_array('publication_issue', data_get($schema, 'required', []), true);
            },
        );
        Gemini::assertSent(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $contentParts = $parameters[0] ?? null;
                $prompt = is_array($contentParts) ? ($contentParts[0] ?? null) : null;

                return $method === 'generateContent'
                    && is_string($prompt)
                    && str_contains($prompt, '2026-yil to\'liq qabul qilinadi')
                    && str_contains($prompt, '2025-yil faqat 3 yoki 4-son');
            },
        );
    }

    public function test_report_dates_cannot_override_the_strict_period(): void
    {
        $result = $this->evaluateResource('2025-09-15', 'current', ScopusCriterionRule::CODE, '2025-10-01', '2026-06-30');

        $this->assertSame('accepted', $result->status);
        $this->assertSame(20.0, $result->point);
    }

    public function test_strict_period_is_enforced_for_every_observation_mode(): void
    {
        $rejected = $this->evaluateResource('2024-09-01', 'previous');
        $accepted = $this->evaluateResource('2025-09-01', 'project_finished');

        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('cancelled', $rejected->status);
    }

    public function test_server_writes_the_period_reason_when_ai_already_rejects_an_outside_date(): void
    {
        $result = $this->evaluateResource(
            '2025-08-31',
            aiStatus: 'cancelled',
            aiReason: 'AI umumiy rad javobini berdi.',
        );

        $this->assertSame('cancelled', $result->status);
        $this->assertSame(0.0, $result->point);
        $this->assertStringContainsString('2025-08-31', $result->reason);
        $this->assertStringContainsString('01.09.2025–31.08.2026', $result->reason);
    }

    #[DataProvider('printedEducationalLiteratureDates')]
    public function test_printed_educational_literature_accepts_publication_years_2025_and_2026(
        string $criterionCode,
        string $resourceDate,
        string $expectedStatus,
        ?string $expectedReason,
    ): void {
        $result = $this->evaluateResource($resourceDate, 'current', $criterionCode);

        $this->assertSame($expectedStatus, $result->status);

        if ($expectedReason !== null) {
            $this->assertStringContainsString($expectedReason, $result->reason);
        }

        Gemini::assertSent(
            resource: GenerativeModel::class,
            model: 'gemini-test',
            callback: function (string $method, array $parameters): bool {
                $contentParts = $parameters[0] ?? null;
                $prompt = is_array($contentParts) ? ($contentParts[0] ?? null) : null;

                return $method === 'generateContent'
                    && is_string($prompt)
                    && str_contains($prompt, '"printed_educational_literature_exception":true')
                    && str_contains($prompt, 'YYYY-MM-DD yoki faqat YYYY')
                    && str_contains($prompt, 'page_count')
                    && str_contains($prompt, 'Pointni o\'zingiz hisoblamang');
            },
        );
    }

    /** @return iterable<string, array{string, string, float, ?string}> */
    public static function publicationDates(): iterable
    {
        yield 'one day before period' => ['2025-08-31', 'cancelled', 0.0, '01.09.2025–31.08.2026'];
        yield 'first day' => ['2025-09-01', 'accepted', 20.0, null];
        yield 'last day' => ['2026-08-31', 'accepted', 20.0, null];
        yield 'one day after period' => ['2026-09-01', 'cancelled', 0.0, '01.09.2025–31.08.2026'];
        yield 'year alone is insufficient' => ['2025', 'checking', 0.0, 'to‘liq sana zarur'];
        yield 'date cannot be determined' => ['', 'checking', 0.0, 'Resurs sanasi aniq topilmadi'];
    }

    /** @return iterable<string, array{string, string, string, ?string}> */
    public static function printedEducationalLiteratureDates(): iterable
    {
        yield 'textbook year 2025' => ['1.2', '2025', 'accepted', null];
        yield 'study guide year 2026' => ['1.3', '2026', 'accepted', null];
        yield 'textbook beginning of 2025' => ['1.2', '2025-01-01', 'accepted', null];
        yield 'study guide end of 2026' => ['1.3', '2026-12-31', 'accepted', null];
        yield 'publication year 2024' => ['1.2', '2024', 'cancelled', '2025 yoki 2026'];
        yield 'publication year 2027' => ['1.3', '2027', 'cancelled', '2025 yoki 2026'];
        yield 'publication year missing' => ['1.2', '', 'checking', 'Resurs sanasi aniq topilmadi'];
    }

    /** @return iterable<string, array{string|null, int, string}> */
    public static function oakArticleYearsAndIssues(): iterable
    {
        yield '2026 year only' => ['2026', 0, 'accepted'];
        yield '2026 exact date' => ['2026-01-01', 1, 'accepted'];
        yield '2025 issue 3' => ['2025', 3, 'accepted'];
        yield '2025 issue 4' => ['2025-01-01', 4, 'accepted'];
        yield '2025 issue 2' => ['2025', 2, 'cancelled'];
        yield '2025 unknown issue' => ['2025', 0, 'checking'];
        yield '2024 issue 4' => ['2024', 4, 'cancelled'];
        yield '2027 issue 3' => ['2027', 3, 'cancelled'];
        yield 'missing publication year' => [null, 0, 'checking'];
    }

    private function evaluateResource(
        string $resourceDate,
        string $observation = 'current',
        string $criterionCode = ScopusCriterionRule::CODE,
        ?string $reportStart = null,
        ?string $reportEnd = null,
        string $aiStatus = 'accepted',
        string $aiReason = 'Q1 Scopus maqolasi tasdiqlandi.',
        ?int $publicationIssue = null,
    ): AiEvaluationResult {
        config()->set('kpi.report_period_start', '2025-09-01');
        config()->set('kpi.report_period_end', '2026-08-31');
        Storage::fake('local');
        $image = UploadedFile::fake()->image('scopus.jpg', 10, 10);
        Storage::disk('local')->put('scopus.jpg', $image->getContent());

        $user = User::factory()->create();
        Formula::query()->updateOrCreate(['id' => 3], [
            'code' => Formula::Unlimited,
            'name' => ['uz' => 'Cheklanmagan'],
            'status' => '1',
        ]);
        Observance::query()->updateOrCreate(['code' => $observation], [
            'name' => ['uz' => 'Joriy davr'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => '2025-2026'],
            'starts_on' => $reportStart,
            'ends_on' => $reportEnd,
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => $criterionCode,
            'name' => ['uz' => $criterionCode === OakArticleCriterionRule::CODE ? 'OAK maqolasi' : ScopusCriterionRule::NAME_UZ],
            'report_id' => $report->id,
            'observation' => $observation,
            'formula_id' => 3,
            'checking' => 'ai',
            'ai_prompt' => $criterionCode === OakArticleCriterionRule::CODE
                ? OakArticleCriterionRule::PROMPT
                : ScopusCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'ai_submission_max_point' => $criterionCode === ScopusCriterionRule::CODE ? 20 : 5,
            'divide_ai_point_by_authors' => false,
            'upload' => '1',
            'status' => '1',
        ]);
        if ($criterionCode === OakArticleCriterionRule::CODE) {
            Evaluation::query()->updateOrCreate(['code' => $user->degree], [
                'name' => ['uz' => 'Ilmiy darajasiz'],
                'status' => '1',
            ]);
            CriterionEvaluation::query()->create([
                'criterion_id' => $criterion->getKey(),
                'evaluation' => $user->degree,
                'has' => '1',
                'score' => 3,
            ]);
        }
        $datum = Datum::query()->create([
            'name' => 'scopus.jpg',
            'material' => [
                'type' => 'file',
                'disk' => 'local',
                'path' => 'scopus.jpg',
            ],
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'status' => 'checking',
            'point' => 0,
        ]);

        Gemini::fake([
            GenerateContentResponse::fake([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode(array_filter([
                                'status' => $aiStatus,
                                'point' => in_array($criterionCode, [ScopusCriterionRule::CODE, OakArticleCriterionRule::CODE], true) ? 0 : 5,
                                'publication_tier' => $criterionCode === ScopusCriterionRule::CODE
                                    ? ($aiStatus === 'accepted' ? 'q1' : 'unknown')
                                    : null,
                                'author_count' => $criterionCode === ScopusCriterionRule::CODE ? null : 4,
                                'page_count' => in_array($criterionCode, ['1.2', '1.3'], true) ? 160 : null,
                                'publication_issue' => $publicationIssue,
                                'resource_date' => $resourceDate,
                                'reason' => $aiReason,
                            ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        return (new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new OakArticleScoreCalculator,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
            new AiResourceDatePolicy,
            new GeminiUrlContextGateway,
            new PrintedEducationalLiteratureScoreCalculator,
            new InternationalCooperationScoreValidator,
            new IndustryFundingScoreCalculator,
        ))->evaluate($datum);
    }
}
