<?php

namespace Tests\Feature;

use App\Data\AiEvaluationResult;
use App\Support\ScopusCriterionRule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use UnexpectedValueException;

class ScopusAiEvaluationTest extends TestCase
{
    /** @return iterable<string, array{string, float}> */
    public static function publicationTiers(): iterable
    {
        yield 'Q1' => ['q1', 20.0];
        yield 'Q2' => ['q2', 15.0];
        yield 'Q3' => ['q3', 10.0];
        yield 'Q4' => ['q4', 5.0];
        yield 'conference' => ['conference', 5.0];
    }

    #[DataProvider('publicationTiers')]
    public function test_server_assigns_point_from_ai_publication_tier(string $tier, float $expectedPoint): void
    {
        $aiResult = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'publication_tier' => $tier,
            'resource_date' => '2026-01-10',
            'reason' => 'Scopus/WoS dalili va nashr turi aniq tasdiqlandi.',
        ], ScopusCriterionRule::MAXIMUM_POINT, requiresPublicationTier: true);

        $serverResult = ScopusCriterionRule::apply($aiResult);

        $this->assertSame($expectedPoint, $serverResult->point);
        $this->assertSame($tier, $serverResult->publicationTier);
    }

    public function test_ai_cannot_supply_the_scopus_point(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 20,
            'publication_tier' => 'q1',
            'resource_date' => '2026-01-10',
            'reason' => 'Q1 deb topildi.',
        ], ScopusCriterionRule::MAXIMUM_POINT, requiresPublicationTier: true);
    }

    public function test_ai_cannot_accept_an_unknown_publication_tier(): void
    {
        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'publication_tier' => 'unknown',
            'resource_date' => '2026-01-10',
            'reason' => 'Kvartil noaniq.',
        ], ScopusCriterionRule::MAXIMUM_POINT, requiresPublicationTier: true);
    }
}
