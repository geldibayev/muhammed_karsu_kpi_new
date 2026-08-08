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

    public function test_it_adds_web_of_science_to_the_higher_scopus_or_research_gate_score(): void
    {
        $calculator = new HIndexScoreCalculator;

        $result = $calculator->calculate([
            'scopus' => ['link' => 'https://scopus.example/profile', 'value' => 7],
            'web_of_science' => ['link' => 'https://wos.example/profile', 'value' => 4],
            'research_gate' => ['link' => 'https://researchgate.example/profile', 'value' => 2],
        ], 3);

        $this->assertSame(7.25, $result['total']);
        $this->assertStringContainsString(
            'Web of Science 2.25 + max(Scopus 5.00, ResearchGate 0.75) = 7.25 ball',
            $result['summary'],
        );
    }

    public function test_it_scores_only_profiles_that_were_entered(): void
    {
        $calculator = new HIndexScoreCalculator;

        $result = $calculator->calculate([
            'scopus' => ['link' => 'https://scopus.example/profile', 'value' => 4],
        ], 3);

        $this->assertSame(2.25, $result['total']);
        $this->assertStringContainsString('Scopus h=4: 2.25 ball', $result['summary']);
    }

    public function test_research_gate_replaces_scopus_only_when_its_score_is_higher(): void
    {
        $calculator = new HIndexScoreCalculator;

        $result = $calculator->calculate([
            'scopus' => ['link' => 'https://scopus.example/profile', 'value' => 3],
            'web_of_science' => ['link' => 'https://wos.example/profile', 'value' => 5],
            'research_gate' => ['link' => 'https://researchgate.example/profile', 'value' => 7],
        ], 3);

        $this->assertSame(8.0, $result['total']);
    }
}
