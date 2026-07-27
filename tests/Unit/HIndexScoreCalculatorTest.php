<?php

namespace Tests\Unit;

use App\Services\HIndexScoreCalculator;
use PHPUnit\Framework\TestCase;

class HIndexScoreCalculatorTest extends TestCase
{
    public function test_it_applies_the_three_point_share_and_extra_points(): void
    {
        $calculator = new HIndexScoreCalculator;

        $this->assertSame(0.75, $calculator->score(2, 3));
        $this->assertSame(1.5, $calculator->score(3, 3));
        $this->assertSame(2.25, $calculator->score(4, 3));
        $this->assertSame(3.0, $calculator->score(5, 3));
        $this->assertSame(5.0, $calculator->score(7, 3));
    }

    public function test_it_sums_the_three_database_scores(): void
    {
        $calculator = new HIndexScoreCalculator;

        $result = $calculator->calculate([
            'scopus' => ['link' => 'https://scopus.example/profile', 'value' => 7],
            'web_of_science' => ['link' => 'https://wos.example/profile', 'value' => 4],
            'research_gate' => ['link' => 'https://researchgate.example/profile', 'value' => 2],
        ], 3);

        $this->assertSame(8.0, $result['total']);
    }
}
