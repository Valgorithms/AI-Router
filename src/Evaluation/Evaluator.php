<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Evaluation;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\JevClient;
use VzgCoders\AiRouter\Model\ModelResult;

final class Evaluator
{
    public function __construct(private readonly ?JevClient $jev) {}

    /** @return PromiseInterface<EvaluationResult> */
    public function evaluate(string $prompt, ModelResult $result, ?string $workdir = null): PromiseInterface
    {
        if (!$result->transportSuccess) {
            return \React\Promise\resolve(new EvaluationResult(false, $result->error ?? 'Model transport failed.', 1.0));
        }
        if ($this->jev === null) {
            return \React\Promise\resolve(new EvaluationResult(true, 'No evaluator configured; response accepted.', 0.0));
        }

        $state = "Task:\n{$prompt}\n\nModel: {$result->model}\n\nResponse:\n{$result->content}";
        return $this->jev->evaluate($state)->then(
            fn(array $decision): EvaluationResult => new EvaluationResult(
                $decision['choice'] === 'success',
                $decision['choice'],
                $decision['confidence'],
            ),
            // Jev being unavailable is not evidence the model failed; accept the transport-successful response.
            fn(\Throwable $e): EvaluationResult => new EvaluationResult(true, 'Jev unavailable; response accepted: ' . $e->getMessage(), 0.0),
        );
    }
}
