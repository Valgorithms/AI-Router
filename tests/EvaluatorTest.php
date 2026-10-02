<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Tests;

use PHPUnit\Framework\TestCase;
use React\Async;
use React\Http\Browser;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\JevClient;
use VzgCoders\AiRouter\Model\ModelResult;
use VzgCoders\AiRouter\Support\HttpClient;

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

    public function testAcceptsResponseWhenJevIsUnreachable(): void
    {
        $jev = new JevClient('key', 'http://127.0.0.1:1', 'jev', 2, new HttpClient(new Browser()));

        $result = Async\await((new Evaluator($jev))->evaluate(
            'Explain this repository',
            new ModelResult('local', 'It routes AI requests.', 0.01, true),
        ));

        self::assertTrue($result->success);
        self::assertStringStartsWith('Jev unavailable', $result->reason);
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
