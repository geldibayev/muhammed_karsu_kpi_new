<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use App\Support\ProfessionalDevelopmentCriterionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProfessionalDevelopmentCriterionRuleTest extends TestCase
{
    #[DataProvider('rankPointProvider')]
    public function test_it_applies_inclusive_ranking_boundaries(
        int $rank,
        float $maximumPoint,
        ?string $expectedTier,
        ?float $expectedPoint,
    ): void {
        $tier = ProfessionalDevelopmentCriterionRule::tierForRank($rank);

        $this->assertSame($expectedTier, $tier);
        $this->assertSame(
            $expectedPoint,
            $tier === null
                ? null
                : ProfessionalDevelopmentCriterionRule::pointForUniversityTier($maximumPoint, $tier),
        );
    }

    /** @return iterable<string, array{int, float, string|null, float|null}> */
    public static function rankPointProvider(): iterable
    {
        yield 'invalid rank zero' => [0, 2.0, null, null];
        yield 'maximum 2, rank 1' => [1, 2.0, 'top_100', 2.0];
        yield 'maximum 2, rank 100' => [100, 2.0, 'top_100', 2.0];
        yield 'maximum 2, rank 101' => [101, 2.0, 'top_300', 1.5];
        yield 'maximum 2, rank 300' => [300, 2.0, 'top_300', 1.5];
        yield 'maximum 2, rank 301' => [301, 2.0, 'top_500', 1.0];
        yield 'maximum 2, rank 500' => [500, 2.0, 'top_500', 1.0];
        yield 'maximum 2, rank 501' => [501, 2.0, 'top_1000', 0.5];
        yield 'maximum 2, rank 1000' => [1000, 2.0, 'top_1000', 0.5];
        yield 'maximum 2, rank 1001' => [1001, 2.0, null, null];
        yield 'maximum 3, rank 100' => [100, 3.0, 'top_100', 3.0];
        yield 'maximum 3, rank 101' => [101, 3.0, 'top_300', 2.25];
        yield 'maximum 3, rank 300' => [300, 3.0, 'top_300', 2.25];
        yield 'maximum 3, rank 301' => [301, 3.0, 'top_500', 1.5];
        yield 'maximum 3, rank 500' => [500, 3.0, 'top_500', 1.5];
        yield 'maximum 3, rank 501' => [501, 3.0, 'top_1000', 0.75];
        yield 'maximum 3, rank 1000' => [1000, 3.0, 'top_1000', 0.75];
        yield 'maximum 3, rank 1001' => [1001, 3.0, null, null];
    }

    public function test_accepted_ai_point_is_replaced_by_server_calculation(): void
    {
        $result = new AiEvaluationResult(
            status: 'accepted',
            point: 0,
            reason: 'Top-101–300 tasdiqlandi.',
            universityTier: 'top_300',
        );

        $calculated = ProfessionalDevelopmentCriterionRule::apply($result, 3.0);

        $this->assertSame('accepted', $calculated->status);
        $this->assertSame(2.25, $calculated->point);
        $this->assertSame('top_300', $calculated->universityTier);
    }

    public function test_invalid_maximum_or_tier_requires_human_review(): void
    {
        $invalidTier = ProfessionalDevelopmentCriterionRule::apply(
            new AiEvaluationResult('accepted', 0, 'Noaniq.', universityTier: 'unknown'),
            3.0,
        );
        $invalidMaximum = ProfessionalDevelopmentCriterionRule::apply(
            new AiEvaluationResult('accepted', 0, 'Tasdiqlandi.', universityTier: 'top_100'),
            4.0,
        );

        $this->assertSame('checking', $invalidTier->status);
        $this->assertSame('checking', $invalidMaximum->status);
        $this->assertSame(0.0, $invalidTier->point);
        $this->assertSame(0.0, $invalidMaximum->point);
    }

    public function test_prompt_forbids_guessing_and_lists_all_inclusive_boundaries(): void
    {
        $prompt = ProfessionalDevelopmentCriterionRule::PROMPT;

        $this->assertStringContainsString('Reyting o‘rnini taxmin qilmang', $prompt);
        $this->assertStringContainsString('1–100 (100 ham kiradi)', $prompt);
        $this->assertStringContainsString('101–300 (300 ham kiradi)', $prompt);
        $this->assertStringContainsString('301–500 (500 ham kiradi)', $prompt);
        $this->assertStringContainsString('501–1000 (1000 ham kiradi)', $prompt);
        $this->assertStringContainsString('100 foizini', $prompt);
        $this->assertStringContainsString('75 foizini', $prompt);
        $this->assertStringContainsString('50 foizini', $prompt);
        $this->assertStringContainsString('25 foizini', $prompt);
    }
}
