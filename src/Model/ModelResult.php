<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

final readonly class ModelResult
{
    public function __construct(
        public string $model,
        public string $content,
        public float $latencySeconds,
        public bool $transportSuccess,
        public ?string $error = null,
    ) {}
}
