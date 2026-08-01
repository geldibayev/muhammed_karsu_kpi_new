<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrintedEducationalLiteratureScoreCalculatorTest extends TestCase
{
    #[DataProvider('scoreCases')]
    public function test_it_calculates_from_pages_rate_and_authors(
        string $criterionCode,
        int $pageCount,
        int $authorCount,
        float $expectedPoint,
    ): void {
        $calculator = new PrintedEducationalLiteratureScoreCalculator;

        $this->assertSame(
            $expectedPoint,
            $calculator->calculate($criterionCode, $pageCount, $authorCount),
        );
    }

    public function test_it_ignores_the_ai_point_and_preserves_raw_evidence(): void
    {
        $result = new AiEvaluationResult(
            status: 'accepted',
            point: 99,
            reason: 'ISBN va ruxsatnoma tasdiqlandi.',
            authorCount: 2,
            resourceDate: '2025',
            pageCount: 160,
        );

        $calculated = (new PrintedEducationalLiteratureScoreCalculator)->apply($result, '1.2');

        $this->assertSame(2.0, $calculated->point);
        $this->assertSame(160, $calculated->pageCount);
        $this->assertSame(2, $calculated->authorCount);
        $this->assertSame('2025', $calculated->resourceDate);
        $this->assertStringContainsString('160 sahifa / 16 × 0.4 / 2 muallif', $calculated->reason);
    }

    public function test_missing_page_count_remains_for_human_review(): void
    {
        $result = new AiEvaluationResult(
            status: 'accepted',
            point: 0,
            reason: 'ISBN tasdiqlandi.',
            authorCount: 2,
        );

        $calculated = (new PrintedEducationalLiteratureScoreCalculator)->apply($result, '1.2');

        $this->assertSame('checking', $calculated->status);
        $this->assertSame(0.0, $calculated->point);
    }

    /** @return iterable<string, array{string, int, int, float}> */
    public static function scoreCases(): iterable
    {
        yield 'textbook ten sheets two authors' => ['1.2', 160, 2, 2.0];
        yield 'study guide ten sheets two authors' => ['1.3', 160, 2, 1.5];
        yield 'fractional sheet is not rounded early' => ['1.2', 17, 3, 0.1417];
        yield 'less than one sheet remains fractional' => ['1.3', 15, 1, 0.2813];
    }
}
