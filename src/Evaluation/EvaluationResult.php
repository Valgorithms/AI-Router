<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Evaluation;

final readonly class EvaluationResult
{
    public function __construct(
        public bool $success,
        public string $reason,
        public float $confidence,
    ) {}
}
