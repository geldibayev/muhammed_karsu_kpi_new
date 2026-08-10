<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use App\Services\OakArticleScoreCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OakArticleScoreCalculatorTest extends TestCase
{
    /** @return array<string, array{string, int, float}> */
    public static function scoringCases(): array
    {
        return [
            'ilmiy darajali' => ['hold_degrees', 4, 0.125],
            'ilmiy darajasiz' => ['no_degrees', 3, 0.25],
            'chet tili kafedrasi' => ['foreign_lang', 2, 0.375],
            'jismoniy tarbiya kafedrasi' => ['physical', 5, 0.15],
        ];
    }

    #[DataProvider('scoringCases')]
    public function test_it_uses_only_scientific_degree_groups(
        string $evaluationCategory,
        int $authorCount,
        float $expectedPoint,
    ): void {
        $calculator = new OakArticleScoreCalculator;

        $this->assertSame($expectedPoint, $calculator->calculate($evaluationCategory, $authorCount));
    }

    public function test_it_replaces_ai_arithmetic_with_server_calculation(): void
    {
        $calculator = new OakArticleScoreCalculator;
        $aiResult = new AiEvaluationResult(
            status: 'accepted',
            point: 99,
            reason: 'OAK jurnali tasdiqlandi.',
            authorCount: 6,
            resourceDate: '2025-10-20',
            publicationIssue: 3,
        );

        $result = $calculator->apply($aiResult, 'hold_degrees');

        $this->assertSame('accepted', $result->status);
        $this->assertSame(0.0833, $result->point);
        $this->assertSame(6, $result->authorCount);
        $this->assertSame('2025-10-20', $result->resourceDate);
        $this->assertSame(3, $result->publicationIssue);
        $this->assertStringContainsString('Taqsimlangan ball: 0.0833', $result->reason);
    }

    public function test_accepted_ai_result_without_author_count_requires_human_review(): void
    {
        $calculator = new OakArticleScoreCalculator;
        $aiResult = new AiEvaluationResult('accepted', 0.5, 'OAK jurnali tasdiqlandi.');

        $result = $calculator->apply($aiResult, 'hold_degrees');

        $this->assertSame('checking', $result->status);
        $this->assertSame(0.0, $result->point);
    }

    public function test_invalid_author_count_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new OakArticleScoreCalculator)->calculate('no_degrees', 0);
    }
}
