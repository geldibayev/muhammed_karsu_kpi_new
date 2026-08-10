<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class AiEvaluationResultTest extends TestCase
{
    public function test_professional_development_requires_valid_top_tier(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'university_tier' => 'top_500',
            'reason' => 'Top-301–500 tasdiqlandi.',
        ], 3, requiresUniversityTier: true);

        $this->assertSame('top_500', $result->universityTier);

        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'university_tier' => 'unknown',
            'reason' => 'Noaniq.',
        ], 3, requiresUniversityTier: true);
    }

    public function test_valid_accepted_json_is_normalized(): void
    {
        $result = AiEvaluationResult::fromJson(
            '{"status":"accepted","point":7.5,"reason":"Talab bajarilgan."}',
            10,
        );

        $this->assertSame('accepted', $result->status);
        $this->assertSame(7.5, $result->point);
        $this->assertSame('Talab bajarilgan.', $result->reason);
    }

    public function test_non_accepted_result_cannot_award_points(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'checking',
            'point' => 5,
            'reason' => 'Inson tekshiruvi kerak.',
        ], 10);

        $this->assertSame(0.0, $result->point);
    }

    public function test_valid_author_count_is_preserved_for_accepted_result(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 1,
            'author_count' => 3,
            'reason' => 'Maqola tasdiqlandi.',
        ], 1);

        $this->assertSame(3, $result->authorCount);
    }

    public function test_valid_resource_date_is_preserved(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 5,
            'resource_date' => '2025-09-01',
            'reason' => 'Maqola tasdiqlandi.',
        ], 5);

        $this->assertSame('2025-09-01', $result->resourceDate);
    }

    public function test_valid_page_count_is_preserved_for_accepted_result(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'author_count' => 2,
            'page_count' => 160,
            'resource_date' => '2025',
            'reason' => 'Darslik tasdiqlandi.',
        ], 100);

        $this->assertSame(160, $result->pageCount);
    }

    public function test_publication_year_is_preserved_for_policy_validation(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 5,
            'resource_date' => '2025',
            'reason' => 'Darslik nashr yili tasdiqlandi.',
        ], 5);

        $this->assertSame('2025', $result->resourceDate);
    }

    public function test_oak_publication_issue_is_validated_and_preserved(): void
    {
        $result = AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'author_count' => 2,
            'publication_issue' => 3,
            'resource_date' => '2025',
            'reason' => 'OAK maqolasi tasdiqlandi.',
        ], 1, requiresPublicationIssue: true);

        $this->assertSame(3, $result->publicationIssue);

        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload([
            'status' => 'accepted',
            'point' => 0,
            'author_count' => 2,
            'publication_issue' => '3',
            'resource_date' => '2025',
            'reason' => 'OAK maqolasi tasdiqlandi.',
        ], 1, requiresPublicationIssue: true);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $payload, float $maximumPoint): void
    {
        $this->expectException(UnexpectedValueException::class);

        AiEvaluationResult::fromPayload($payload, $maximumPoint);
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->expectException(JsonException::class);

        AiEvaluationResult::fromJson('{invalid', 10);
    }

    /** @return iterable<string, array{array<string, mixed>, float}> */
    public static function invalidPayloads(): iterable
    {
        yield 'unknown status' => [[
            'status' => 'received',
            'point' => 1,
            'reason' => 'Xulosa',
        ], 10];

        yield 'numeric string' => [[
            'status' => 'accepted',
            'point' => '5',
            'reason' => 'Xulosa',
        ], 10];

        yield 'negative point' => [[
            'status' => 'accepted',
            'point' => -1,
            'reason' => 'Xulosa',
        ], 10];

        yield 'point over cap' => [[
            'status' => 'accepted',
            'point' => 11,
            'reason' => 'Xulosa',
        ], 10];

        yield 'empty reason' => [[
            'status' => 'accepted',
            'point' => 1,
            'reason' => ' ',
        ], 10];

        yield 'unexpected field' => [[
            'status' => 'accepted',
            'point' => 1,
            'reason' => 'Xulosa',
            'html' => '<script>alert(1)</script>',
        ], 10];

        yield 'invalid accepted author count' => [[
            'status' => 'accepted',
            'point' => 1,
            'author_count' => 0,
            'reason' => 'Xulosa',
        ], 10];

        yield 'cancelled point over cap' => [[
            'status' => 'cancelled',
            'point' => 11,
            'reason' => 'Xulosa',
        ], 10];

        yield 'invalid resource date' => [[
            'status' => 'accepted',
            'point' => 1,
            'resource_date' => '01.09.2025',
            'reason' => 'Xulosa',
        ], 10];

        yield 'invalid accepted page count' => [[
            'status' => 'accepted',
            'point' => 0,
            'author_count' => 1,
            'page_count' => 0,
            'resource_date' => '2025',
            'reason' => 'Xulosa',
        ], 10];
    }
}
