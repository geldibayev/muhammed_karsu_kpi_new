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

    #[DataProvider('rankTierProvider')]
    public function test_ranking_boundaries_are_inclusive(int $rank, ?string $expectedTier): void
    {
        $this->assertSame($expectedTier, InternationalCooperationCriterionRule::tierForRank($rank));
    }

    /** @return iterable<string, array{int, string|null}> */
    public static function rankTierProvider(): iterable
    {
        yield 'invalid zero' => [0, null];
        yield 'Top-100 upper boundary' => [100, 'top_100'];
        yield 'Top-300 lower boundary' => [101, 'top_300'];
        yield 'Top-300 upper boundary' => [300, 'top_300'];
        yield 'Top-500 lower boundary' => [301, 'top_500'];
        yield 'Top-500 upper boundary' => [500, 'top_500'];
        yield 'Top-1000 lower boundary' => [501, 'top_1000'];
        yield 'Top-1000 upper boundary' => [1000, 'top_1000'];
        yield 'outside Top-1000' => [1001, 'outside_top_1000'];
    }

    #[DataProvider('universityTierPointProvider')]
    public function test_ai_only_selects_the_tier_and_server_calculates_the_point(
        float $maximumPoint,
        string $universityTier,
        float $expectedPoint,
    ): void {
        $result = new AiEvaluationResult(
            'accepted',
            0,
            'Rasmiy dalil tasdiqlandi.',
            universityTier: $universityTier,
        );

        $validated = (new InternationalCooperationScoreValidator)->handle($result, $maximumPoint);

        $this->assertSame('accepted', $validated->status);
        $this->assertSame($expectedPoint, $validated->point);
        $this->assertSame($universityTier, $validated->universityTier);
    }

    #[DataProvider('universityTierPointProvider')]
    public function test_it_calculates_human_review_point_from_the_university_tier(
        float $maximumPoint,
        string $universityTier,
        float $expectedPoint,
    ): void {
        $this->assertSame(
            $expectedPoint,
            InternationalCooperationCriterionRule::pointForUniversityTier($maximumPoint, $universityTier),
        );
    }

    /** @return iterable<string, array{float, string, float}> */
    public static function universityTierPointProvider(): iterable
    {
        yield 'standard Top-100' => [3.0, 'top_100', 3.0];
        yield 'standard Top-300' => [3.0, 'top_300', 2.25];
        yield 'standard Top-500' => [3.0, 'top_500', 1.5];
        yield 'standard Top-1000' => [3.0, 'top_1000', 0.75];
        yield 'standard foreign students' => [3.0, 'foreign_students', 3.0];
        yield 'special Top-100' => [4.0, 'top_100', 4.0];
        yield 'special Top-300' => [4.0, 'top_300', 3.0];
        yield 'special Top-500' => [4.0, 'top_500', 2.0];
        yield 'special Top-1000' => [4.0, 'top_1000', 1.0];
        yield 'special foreign students' => [4.0, 'foreign_students', 4.0];
    }

    #[DataProvider('invalidTierProvider')]
    public function test_it_sends_invalid_accepted_tiers_to_human_review(
        float $maximumPoint,
        ?string $universityTier,
    ): void {
        $result = new AiEvaluationResult(
            'accepted',
            0,
            'AI noto‘g‘ri Top darajasini qaytardi.',
            universityTier: $universityTier,
        );

        $validated = (new InternationalCooperationScoreValidator)->handle($result, $maximumPoint);

        $this->assertSame('checking', $validated->status);
        $this->assertSame(0.0, $validated->point);
    }

    /** @return iterable<string, array{float, string|null}> */
    public static function invalidTierProvider(): iterable
    {
        yield 'missing tier' => [3.0, null];
        yield 'unknown tier' => [3.0, 'unknown'];
        yield 'unsupported maximum' => [5.0, 'top_100'];
    }

    public function test_prompt_requires_only_tier_classification_and_server_calculation(): void
    {
        $prompt = InternationalCooperationCriterionRule::PROMPT;

        $this->assertStringContainsString('Bu ilmiy maqola mezoni emas', $prompt);
        $this->assertStringContainsString('Faqat bitta yuklangan fayl baholanadi', $prompt);
        $this->assertStringContainsString('barcha hujjatlarni birgalikda talab qilmang', $prompt);
        $this->assertStringContainsString('Top-101–300 — 75%', $prompt);
        $this->assertStringContainsString('Top-301–500 — 50%', $prompt);
        $this->assertStringContainsString('Top-501–1000 — 25%', $prompt);
        $this->assertStringContainsString('xorijlik talabalarni jalb qilish — 100%', $prompt);
        $this->assertStringContainsString('Al-Farabi Kazakh National University, KazNU', $prompt);
        $this->assertStringContainsString('university_tier uchun top_300', $prompt);
        $this->assertStringContainsString('point maydoniga qat\'iy 0', $prompt);
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

        $this->assertStringContainsString('(ekspert, mutaxassis)', data_get($criterion->name, 'uz'));
        $this->assertStringNotContainsString('yekspert', data_get($criterion->name, 'uz'));
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

        $migration = require database_path('migrations/2026_08_24_160844_update_criterion_2_1_6_percentage_scoring.php');
        $migration->up();

        $criterion->refresh()->load('criterionEvaluations');

        $this->assertSame(InternationalCooperationCriterionRule::PROMPT, $criterion->ai_prompt);
        $this->assertSame(
            InternationalCooperationCriterionRule::DESCRIPTION_UZ,
            data_get($criterion->desc, 'uz'),
        );
        $this->assertSame(1, $criterion->file_limit);
        $this->assertSame('file', $criterion->res_type);
        $this->assertFalse($criterion->divide_ai_point_by_authors);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'hold_degrees')?->score);
        $this->assertSame(3, $criterion->criterionEvaluations->firstWhere('evaluation', 'no_degrees')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'foreign_lang')?->score);
        $this->assertSame(4, $criterion->criterionEvaluations->firstWhere('evaluation', 'physical')?->score);
    }

    public function test_name_typo_migration_replaces_yekspert_with_ekspert(): void
    {
        $report = Report::query()->create([
            'name' => ['uz' => 'Nom tuzatish testi'],
            'status' => '1',
        ]);
        $criterion = Criterion::query()->create([
            'code' => InternationalCooperationCriterionRule::CODE,
            'name' => [
                'uz' => 'Xorijlik olimlar(yekspert, mutaxassis)ni jalb qilish',
                'en' => 'Involving foreign scientists',
            ],
            'report_id' => $report->id,
            'checking' => 'ai',
            'upload' => '1',
            'status' => '1',
        ]);

        $migration = require database_path('migrations/2026_08_24_164311_fix_expert_typo_in_criterion_2_1_6_name.php');
        $migration->up();

        $criterion->refresh();

        $this->assertSame('Xorijlik olimlar(ekspert, mutaxassis)ni jalb qilish', data_get($criterion->name, 'uz'));
        $this->assertSame('Involving foreign scientists', data_get($criterion->name, 'en'));
    }
}
