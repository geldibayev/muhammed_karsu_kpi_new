<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\Evaluation;
use App\Models\Report;
use App\Services\InternationalCooperationScoreValidator;
use App\Support\InternationalCooperationCriterionRule;
use Database\Seeders\CriterionSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\OptionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternationalCooperationAiEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('allowedPointProvider')]
    public function test_it_accepts_only_points_from_the_category_ladder(float $maximumPoint, float $point): void
    {
        $result = new AiEvaluationResult('accepted', $point, 'Rasmiy dalil tasdiqlandi.');

        $validated = (new InternationalCooperationScoreValidator)->handle($result, $maximumPoint);

        $this->assertSame('accepted', $validated->status);
        $this->assertSame($point, $validated->point);
    }

    /** @return iterable<string, array{float, float}> */
    public static function allowedPointProvider(): iterable
    {
        yield 'standard Top-1000' => [3.0, 1.5];
        yield 'standard Top-500' => [3.0, 2.0];
        yield 'standard Top-300' => [3.0, 2.5];
        yield 'standard Top-100 or foreign student' => [3.0, 3.0];
        yield 'special Top-1000' => [4.0, 1.0];
        yield 'special Top-500' => [4.0, 3.0];
        yield 'special Top-300' => [4.0, 3.5];
        yield 'special Top-100 or foreign student' => [4.0, 4.0];
    }

    #[DataProvider('invalidPointProvider')]
    public function test_it_sends_invalid_accepted_points_to_human_review(float $maximumPoint, float $point): void
    {
        $result = new AiEvaluationResult('accepted', $point, 'AI noto‘g‘ri ball qaytardi.');

        $validated = (new InternationalCooperationScoreValidator)->handle($result, $maximumPoint);

        $this->assertSame('checking', $validated->status);
        $this->assertSame(0.0, $validated->point);
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidPointProvider(): iterable
    {
        yield 'arbitrary standard point' => [3.0, 2.7];
        yield 'special point on standard ladder' => [3.0, 3.5];
        yield 'standard Top-1000 point on special ladder' => [4.0, 1.5];
        yield 'unsupported maximum' => [5.0, 4.0];
    }

    public function test_prompt_is_single_file_non_article_and_contains_both_category_ladders(): void
    {
        $prompt = InternationalCooperationCriterionRule::PROMPT;

        $this->assertStringContainsString('Bu ilmiy maqola mezoni emas', $prompt);
        $this->assertStringContainsString('Faqat bitta yuklangan fayl baholanadi', $prompt);
        $this->assertStringContainsString('barcha hujjatlarni birgalikda talab qilmang', $prompt);
        $this->assertStringContainsString('Maksimal ruxsat etilgan ball 3', $prompt);
        $this->assertStringContainsString('Top-501–1000 = 1.5', $prompt);
        $this->assertStringContainsString('Maksimal ruxsat etilgan ball 4', $prompt);
        $this->assertStringContainsString('Top-501–1000 = 1', $prompt);
        $this->assertStringContainsString('xorijlik talabalarni jalb qilish = 4', $prompt);
        $this->assertStringContainsString('reytingni taxmin qilmang', $prompt);
    }

    public function test_criterion_seeder_applies_the_prompt_file_limit_and_category_maximums(): void
    {
        $this->seed(OptionSeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(CriterionSeeder::class);

        $criterion = Criterion::query()
            ->with('criterionEvaluations')
            ->where('code', InternationalCooperationCriterionRule::CODE)
            ->firstOrFail();

        $this->assertSame(InternationalCooperationCriterionRule::PROMPT, $criterion->ai_prompt);
        $this->assertSame(1, $criterion->file_limit);
        $this->assertSame('file', $criterion->res_type);
        $this->assertFalse($criterion->divide_ai_point_by_authors);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'hold_degrees')?->score);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'no_degrees')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'foreign_lang')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'physical')?->score);
    }

    public function test_configuration_migration_repairs_a_corrupted_production_prompt_and_scores(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Production prompt tuzatish testi'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => InternationalCooperationCriterionRule::CODE,
            'name' => ['uz' => 'Xalqaro hamkorlik'],
            'report_id' => $report->id,
            'checking' => 'ai',
            'ai_prompt' => 'Ilmiy maqola va jurnal impakt-faktorini tekshiring.',
            'file_limit' => 0,
            'res_type' => 'all',
            'divide_ai_point_by_authors' => true,
            'upload' => '1',
            'status' => '1',
        ]);

        foreach (['hold_degrees', 'no_degrees', 'foreign_lang', 'physical'] as $evaluationCode) {
            Evaluation::query()->create([
                'code' => $evaluationCode,
                'name' => ['uz' => $evaluationCode],
                'status' => '1',
            ]);
        }

        CriterionEvaluation::query()->create([
            'criterion_id' => $criterion->id,
            'evaluation' => 'foreign_lang',
            'has' => '1',
            'score' => 3,
        ]);

        $migration = require database_path('migrations/2026_08_02_181726_fix_criterion_2_1_6_ai_configuration.php');
        $migration->up();

        $criterion->refresh()->load('criterionEvaluations');

        $this->assertSame(InternationalCooperationCriterionRule::PROMPT, $criterion->ai_prompt);
        $this->assertSame(1, $criterion->file_limit);
        $this->assertSame('file', $criterion->res_type);
        $this->assertFalse($criterion->divide_ai_point_by_authors);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'hold_degrees')?->score);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'no_degrees')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'foreign_lang')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'physical')?->score);
    }
}
