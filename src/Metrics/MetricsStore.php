<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Metrics;

use React\Filesystem\Filesystem;
use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Evaluation\EvaluationResult;
use VzgCoders\AiRouter\Model\ModelResult;

final class MetricsStore
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $path,
    ) {}

    /** @return PromiseInterface<null> */
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

        return $this->filesystem->directory($directory)->createRecursive()
            ->then(fn () => $this->filesystem->file($this->path)->append($entry));
    }
}
