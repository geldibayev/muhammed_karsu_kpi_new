<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use App\Services\AiAuthorPointDistributor;
use PHPUnit\Framework\TestCase;

class AiAuthorPointDistributorTest extends TestCase
{
    public function test_it_divides_an_accepted_point_by_author_count(): void
    {
        $result = (new AiAuthorPointDistributor)->handle(
            new AiEvaluationResult('accepted', 1, 'Q1 maqola.', 4),
            'author_count',
        );

        $this->assertSame('accepted', $result->status);
        $this->assertSame(0.25, $result->point);
        $this->assertSame(4, $result->authorCount);
        $this->assertStringContainsString('Taqsimlangan ball: 0.2500', $result->reason);
    }

    public function test_it_leaves_criteria_without_author_distribution_unchanged(): void
    {
        $original = new AiEvaluationResult('accepted', 2, 'Tasdiqlandi.');

        $result = (new AiAuthorPointDistributor)->handle($original, 'Muallif ulushi talab qilinmaydi.');

        $this->assertSame($original, $result);
    }

    public function test_it_keeps_full_point_when_author_division_is_explicitly_disabled(): void
    {
        $original = new AiEvaluationResult('accepted', 5, 'Q1 maqola.', 4);

        $result = (new AiAuthorPointDistributor)->handle(
            $original,
            'author_count',
            false,
        );

        $this->assertSame($original, $result);
        $this->assertSame(5.0, $result->point);
    }

    public function test_it_does_not_divide_a_point_that_the_prompt_already_distributed(): void
    {
        $original = new AiEvaluationResult('accepted', 1.5, 'Tasdiqlandi.', 2);

        $result = (new AiAuthorPointDistributor)->handle(
            $original,
            "point qismiga qiymatni mualliflar soniga bo'lib yozing va author_count qaytaring",
        );

        $this->assertSame($original, $result);

        $formulaResult = (new AiAuthorPointDistributor)->handle(
            $original,
            'point = bosma_tabog‘i * 0.3 / mualliflar_soni, author_count qaytarilsin',
        );

        $this->assertSame($original, $formulaResult);
    }

    public function test_missing_required_author_count_remains_for_human_review(): void
    {
        $result = (new AiAuthorPointDistributor)->handle(
            new AiEvaluationResult('accepted', 1, 'Tasdiqlandi.'),
            'author_count',
        );

        $this->assertSame('checking', $result->status);
        $this->assertSame(0.0, $result->point);
    }
}
