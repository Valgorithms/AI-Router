<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Support\HttpClient;

final class ClaudeModel implements ModelInterface
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
        return 'claude';
    }

    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
    {
        $started = microtime(true);
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($systemPrompt !== '') {
            $body['system'] = $systemPrompt;
        }

        return $this->http
            ->postJson(
                rtrim($this->baseUrl, '/') . '/v1/messages',
                [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                $body,
                $this->timeout,
            )
            ->then(function (array $response) use ($started): ModelResult {
                $parts = [];
                foreach (($response['content'] ?? []) as $block) {
                    if (($block['type'] ?? null) === 'text') {
                        $parts[] = (string) ($block['text'] ?? '');
                    }
                }
                $text = trim(implode("\n", $parts));
                if ($text === '') {
                    throw new \RuntimeException('Claude returned no text content.');
                }
                return new ModelResult($this->name(), $text, microtime(true) - $started, true);
            }, function (\Throwable $e) use ($started): ModelResult {
                return new ModelResult($this->name(), '', microtime(true) - $started, false, $e->getMessage());
            });
    }
}
