<?php

namespace Tests\Unit;

use App\Data\AiEvaluationResult;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class AiEvaluationResultTest extends TestCase
{
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

        yield 'cancelled point over cap' => [[
            'status' => 'cancelled',
            'point' => 11,
            'reason' => 'Xulosa',
        ], 10];
    }
}
