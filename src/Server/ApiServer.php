<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Router;

/** Minimal OpenAI-compatible facade over the router for Copilot Chat "custom model" use. */
final class ApiServer
{
    public function __construct(
        private readonly Router $router,
        private readonly string $modelId,
        private readonly ?string $apiKey = null,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface|PromiseInterface
    {
        if ($this->apiKey !== null && $this->apiKey !== ''
            && !hash_equals('Bearer ' . $this->apiKey, $request->getHeaderLine('Authorization'))) {
            return self::error(401, 'Invalid API key.', 'invalid_request_error');
        }

        $path = rtrim($request->getUri()->getPath(), '/');
        $method = $request->getMethod();

        if ($method === 'GET' && $path === '/v1/models') {
            return self::json(200, [
                'object' => 'list',
                'data' => [['id' => $this->modelId, 'object' => 'model', 'created' => 0, 'owned_by' => 'ai-router']],
            ]);
        }

        if ($method === 'POST' && $path === '/v1/chat/completions') {
            return $this->chatCompletions($request);
        }

        return self::error(404, 'Not found.', 'invalid_request_error');
    }

    /** @param list<mixed> $messages */
    public static function buildPrompt(array $messages): string
    {
        $parts = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $text = self::contentToText($message['content'] ?? '');
            if ($text === '') {
                continue;
            }
            $role = match ($message['role'] ?? 'user') {
                'system', 'developer' => 'System',
                'assistant' => 'Assistant',
                'tool' => 'Tool',
                default => 'User',
            };
            $parts[] = "{$role}:\n{$text}";
        }

        return implode("\n\n", $parts);
    }

    private function chatCompletions(ServerRequestInterface $request): ResponseInterface|PromiseInterface
    {
        try {
            $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400, 'Request body must be valid JSON.', 'invalid_request_error');
        }

        $prompt = is_array($body) && is_array($body['messages'] ?? null) ? self::buildPrompt($body['messages']) : '';
        if ($prompt === '') {
            return self::error(400, '"messages" must contain at least one non-empty message.', 'invalid_request_error');
        }

        $stream = ($body['stream'] ?? false) === true;

        return $this->router->run($prompt)->then(
            function (array $outcome) use ($stream): ResponseInterface {
                $result = $outcome['result'];
                if (!$result->transportSuccess) {
                    return self::error(502, $result->error ?? 'All models failed.', 'upstream_error');
                }

                return $stream
                    ? $this->streamResponse($result->content)
                    : self::json(200, [
                        'id' => 'chatcmpl-' . bin2hex(random_bytes(8)),
                        'object' => 'chat.completion',
                        'created' => time(),
                        'model' => $this->modelId,
                        'choices' => [[
                            'index' => 0,
                            'message' => ['role' => 'assistant', 'content' => $result->content],
                            'finish_reason' => 'stop',
                        ]],
                        'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                        'x_router_model' => $result->model,
                    ]);
            },
            fn(\Throwable $e): ResponseInterface => self::error(500, $e->getMessage(), 'server_error'),
        );
    }

    /** The router returns complete responses, so streaming is emulated with a single content chunk. */
    private function streamResponse(string $content): ResponseInterface
    {
        $id = 'chatcmpl-' . bin2hex(random_bytes(8));
        $chunk = fn(array $delta, ?string $finish): string => 'data: ' . json_encode([
            'id' => $id,
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $this->modelId,
            'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]],
        ], JSON_THROW_ON_ERROR) . "\n\n";

        $body = $chunk(['role' => 'assistant', 'content' => $content], null)
            . $chunk([], 'stop')
            . "data: [DONE]\n\n";

        return new Response(200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache'], $body);
    }

    private static function contentToText(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }
        if (!is_array($content)) {
            return '';
        }

        $texts = [];
        foreach ($content as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }

    /** @param array<string, mixed> $data */
    private static function json(int $status, array $data): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function error(int $status, string $message, string $type): ResponseInterface
    {
        return self::json($status, ['error' => ['message' => $message, 'type' => $type]]);
    }
}
