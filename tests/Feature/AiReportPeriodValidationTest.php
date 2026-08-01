<?php

namespace Tests\Feature;

use App\Actions\DescribeAiFailure;
use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Observance;
use App\Models\Report;
use App\Models\User;
use App\Services\AiAuthorPointDistributor;
use App\Services\AiSubmissionEvaluator;
use App\Services\GeminiFileMimeTypeResolver;
use App\Support\ScopusCriterionRule;
use Gemini\Laravel\Facades\Gemini;
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
    ): void {
        $result = $this->evaluateScopusArticle($resourceDate);

        $this->assertSame($expectedStatus, $result->status);
        $this->assertSame($expectedPoint, $result->point);
    }

    public function test_scopus_point_is_not_divided_between_authors(): void
    {
        $result = $this->evaluateScopusArticle('2026-07-31');

        $this->assertSame('accepted', $result->status);
        $this->assertSame(5.0, $result->point);
        $this->assertSame(4, $result->authorCount);
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function publicationDates(): iterable
    {
        yield 'one day before period' => ['2025-08-31', 'cancelled', 0.0];
        yield 'first day' => ['2025-09-01', 'accepted', 5.0];
        yield 'last day' => ['2026-07-31', 'accepted', 5.0];
        yield 'one day after period' => ['2026-08-01', 'cancelled', 0.0];
        yield 'date cannot be determined' => ['', 'checking', 0.0];
    }

    private function evaluateScopusArticle(string $resourceDate): AiEvaluationResult
    {
        config()->set('kpi.report_period_start', '2025-09-01');
        config()->set('kpi.report_period_end', '2026-07-31');
        Storage::fake('local');
        $image = UploadedFile::fake()->image('scopus.jpg', 10, 10);
        Storage::disk('local')->put('scopus.jpg', $image->getContent());

        $user = User::factory()->create();
        Formula::query()->create([
            'id' => 3,
            'name' => ['uz' => 'Cheklanmagan'],
            'status' => '1',
        ]);
        Observance::query()->create([
            'code' => 'current',
            'name' => ['uz' => 'Joriy davr'],
            'status' => '1',
        ]);
        $report = Report::query()->create([
            'name' => ['uz' => '2025-2026'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'name' => ['uz' => ScopusCriterionRule::NAME_UZ],
            'report_id' => $report->id,
            'observation' => 'current',
            'formula_id' => 3,
            'checking' => 'ai',
            'ai_prompt' => ScopusCriterionRule::PROMPT,
            'ai_model' => 'gemini-test',
            'ai_submission_max_point' => 5,
            'divide_ai_point_by_authors' => false,
            'upload' => '1',
            'status' => '1',
        ]);
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
                            'text' => json_encode([
                                'status' => 'accepted',
                                'point' => 5,
                                'author_count' => 4,
                                'resource_date' => $resourceDate,
                                'reason' => 'Q1 Scopus maqolasi tasdiqlandi.',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        return (new AiSubmissionEvaluator(
            new AiAuthorPointDistributor,
            new DescribeAiFailure,
            new GeminiFileMimeTypeResolver,
        ))->evaluate($datum);
    }
}
