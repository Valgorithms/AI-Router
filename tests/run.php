<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use React\Async;
use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Evaluation\EvaluationResult;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\Model\ModelInterface;
use VzgCoders\AiRouter\Model\ModelResult;

final class FakeModel implements ModelInterface
{
    public function __construct(private readonly string $id, private readonly string $content) {}
    public function name(): string { return $this->id; }
    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
    {
        return \React\Promise\resolve(new ModelResult($this->id, $this->content, 0.01, true));
    }
}

assert((new FakeModel('test', 'ok'))->name() === 'test');
assert(new EvaluationResult(true, 'ok', 1.0)->success === true);
assert((new Evaluator(null))->evaluate('test', new ModelResult('test', 'ok', 0.01, true))->then(fn ($r) => assert($r->success === true)) instanceof PromiseInterface);

Async\await(\React\Promise\resolve(null));
echo "All tests passed.\n";
