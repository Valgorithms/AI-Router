<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\Promise\PromiseInterface;

interface ModelInterface
{
    public function name(): string;

    /** @return PromiseInterface<ModelResult> */
    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface;
}
