<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Tests;

use PHPUnit\Framework\TestCase;
use React\Async;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\Model\ModelResult;

final class EvaluatorTest extends TestCase
{
    public function testAcceptsSuccessfulModelResponseWhenJevIsNotConfigured(): void
    {
        $result = Async\await((new Evaluator(null))->evaluate(
            'Explain this repository',
            new ModelResult('test-model', 'It routes AI requests.', 0.01, true),
        ));

        self::assertTrue($result->success);
        self::assertSame('No evaluator configured; response accepted.', $result->reason);
        self::assertSame(0.0, $result->confidence);
    }

    public function testRejectsModelTransportFailure(): void
    {
        $result = Async\await((new Evaluator(null))->evaluate(
            'Explain this repository',
            new ModelResult('test-model', '', 0.01, false, 'Connection failed.'),
        ));

        self::assertFalse($result->success);
        self::assertSame('Connection failed.', $result->reason);
        self::assertSame(1.0, $result->confidence);
    }
}
