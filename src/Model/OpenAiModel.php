<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Support\HttpClient;

final class OpenAiModel implements ModelInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly int $timeout,
        private readonly HttpClient $http,
    ) {}

    public function name(): string
    {
        return 'openai';
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
            'max_completion_tokens' => $this->maxTokens,
        ];

        return $this->http
            ->postJson(
                rtrim($this->baseUrl, '/') . '/chat/completions',
                ['Authorization' => 'Bearer ' . $this->apiKey],
                $body,
                $this->timeout,
            )
            ->then(function (array $response) use ($started): ModelResult {
                $text = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
                if ($text === '') {
                    throw new \RuntimeException('OpenAI returned no message content.');
                }
                return new ModelResult($this->name(), $text, microtime(true) - $started, true);
            }, function (\Throwable $e) use ($started): ModelResult {
                return new ModelResult($this->name(), '', microtime(true) - $started, false, $e->getMessage());
            });
    }
}
