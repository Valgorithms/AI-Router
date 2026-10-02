<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Support\HttpClient;

final class OllamaModel implements ModelInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $context,
        private readonly int $timeout,
        private readonly bool $think,
        private readonly HttpClient $http,
    ) {}

    public function name(): string
    {
        return 'local';
    }

    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
    {
        $started = microtime(true);
        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['num_ctx' => $this->context],
        ];
        if ($this->think) {
            $body['think'] = true;
        }

        return $this->http
            ->postJson(rtrim($this->baseUrl, '/') . '/chat/completions', [], $body, $this->timeout)
            ->then(function (array $response) use ($started): ModelResult {
                $text = (string) ($response['choices'][0]['message']['content'] ?? '');
                if ($text === '') {
                    throw new \RuntimeException('Ollama returned no message content.');
                }
                return new ModelResult($this->name(), $text, microtime(true) - $started, true);
            }, function (\Throwable $e) use ($started): ModelResult {
                return new ModelResult($this->name(), '', microtime(true) - $started, false, $e->getMessage());
            });
    }
}
