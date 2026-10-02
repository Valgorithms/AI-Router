<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Metrics;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Evaluation\EvaluationResult;
use VzgCoders\AiRouter\Model\ModelResult;

final class MetricsStore
{
    public function __construct(
        private readonly string $path,
    ) {}

    /** @return PromiseInterface<null> Metrics failures never fail routing; the promise always resolves. */
    public function record(string $prompt, ModelResult $result, EvaluationResult $evaluation): PromiseInterface
    {
        $directory = dirname($this->path);
        $entry = json_encode([
            'timestamp' => gmdate(DATE_ATOM),
            'model' => $result->model,
            'success' => $evaluation->success,
            'reason' => $evaluation->reason,
            'confidence' => $evaluation->confidence,
            'transport_success' => $result->transportSuccess,
            'latency_seconds' => $result->latencySeconds,
            'prompt_length' => strlen($prompt),
            'error' => $result->error,
        ], JSON_THROW_ON_ERROR) . PHP_EOL;

        if (is_dir($directory) || @mkdir($directory, 0o777, true) || is_dir($directory)) {
            // Regular files cannot use non-blocking streams (notably on Windows); one small append is acceptable.
            @file_put_contents($this->path, $entry, FILE_APPEND | LOCK_EX);
        }

        return \React\Promise\resolve(null);
    }
}
