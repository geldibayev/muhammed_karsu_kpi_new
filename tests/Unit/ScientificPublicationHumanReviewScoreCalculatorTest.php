<?php

namespace Tests\Unit;

use App\Services\ScientificPublicationHumanReviewScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScientificPublicationHumanReviewScoreCalculatorTest extends TestCase
{
    /** @return array<string, array{float, int, float}> */
    public static function impactFactorCases(): array
    {
        return [
            'one is ten percent' => [2, 1, 0.2],
            'two is twenty percent' => [3, 2, 0.6],
            'ten is maximum' => [3, 10, 3],
            'above ten remains maximum' => [2, 27, 2],
        ];
    }

    #[DataProvider('impactFactorCases')]
    public function test_impact_factor_uses_ten_percent_steps(
        float $maximumPoint,
        int $impactFactor,
        float $expectedPoint,
    ): void {
        $calculator = new ScientificPublicationHumanReviewScoreCalculator;

        $this->assertSame($expectedPoint, $calculator->impactFactorPoint($maximumPoint, $impactFactor));
    }

    /** @return array<string, array{string, float}> */
    public static function publicationTierCases(): array
    {
        return [
            'Q1' => ['q1', 20],
            'Q2' => ['q2', 15],
            'Q3' => ['q3', 10],
            'Q4' => ['q4', 5],
            'conference' => ['conference', 5],
        ];
    }

    #[DataProvider('publicationTierCases')]
    public function test_publication_tier_has_fixed_point(string $tier, float $expectedPoint): void
    {
        $calculator = new ScientificPublicationHumanReviewScoreCalculator;

        $this->assertSame($expectedPoint, $calculator->publicationTierPoint($tier));
    }

    public function test_author_point_is_divided_equally(): void
    {
        $calculator = new ScientificPublicationHumanReviewScoreCalculator;

        $this->assertSame(1.5, $calculator->authorDividedPoint(3, 2));
        $this->assertSame(1.0, $calculator->authorDividedPoint(4, 4));
    }
}
