<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Tests;

use PHPUnit\Framework\TestCase;
use React\Async;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\Metrics\MetricsStore;
use VzgCoders\AiRouter\Model\ModelInterface;
use VzgCoders\AiRouter\Model\ModelResult;
use VzgCoders\AiRouter\Router;
use VzgCoders\AiRouter\Server\ApiServer;

final class ApiServerTest extends TestCase
{
    public function testBuildPromptFlattensRolesAndContentParts(): void
    {
        $prompt = ApiServer::buildPrompt([
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hi'], ['type' => 'image_url']]],
        ]);

        self::assertSame("System:\nBe brief.\n\nUser:\nHi", $prompt);
    }

    public function testFallsBackToSecondModelWhenFirstFails(): void
    {
        $server = $this->server([
            'local' => $this->model('local', false),
            'copilot' => $this->model('copilot', true),
        ]);

        $response = Async\await($server($this->chatRequest(['messages' => [['role' => 'user', 'content' => 'Hi']]])));
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('copilot answer', $data['choices'][0]['message']['content']);
        self::assertSame('copilot', $data['x_router_model']);
    }

    public function testStreamingReturnsServerSentEvents(): void
    {
        $server = $this->server(['local' => $this->model('local', true)]);

        $response = Async\await($server($this->chatRequest(['stream' => true, 'messages' => [['role' => 'user', 'content' => 'Hi']]])));

        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('local answer', (string) $response->getBody());
        self::assertStringEndsWith("data: [DONE]\n\n", (string) $response->getBody());
    }

    public function testReturnsBadGatewayWhenAllModelsFail(): void
    {
        $server = $this->server(['local' => $this->model('local', false)]);

        $response = Async\await($server($this->chatRequest(['messages' => [['role' => 'user', 'content' => 'Hi']]])));

        self::assertSame(502, $response->getStatusCode());
    }

    public function testRejectsInvalidApiKey(): void
    {
        $server = $this->server(['local' => $this->model('local', true)], 'secret');

        $response = $server(new ServerRequest('GET', 'http://localhost/v1/models'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testListsModel(): void
    {
        $server = $this->server(['local' => $this->model('local', true)], 'secret');

        $response = $server(new ServerRequest('GET', 'http://localhost/v1/models', ['Authorization' => 'Bearer secret']));

        self::assertSame('ai-router', json_decode((string) $response->getBody(), true)['data'][0]['id']);
    }

    /** @param array<string, ModelInterface> $models */
    private function server(array $models, ?string $apiKey = null): ApiServer
    {
        $metrics = new MetricsStore(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-router-test-' . bin2hex(random_bytes(4)) . '.jsonl',
        );
        $router = new Router($models, null, new Evaluator(null), $metrics, true, 0.8, 3);

        return new ApiServer($router, 'ai-router', $apiKey);
    }

    private function model(string $name, bool $succeeds): ModelInterface
    {
        return new class ($name, $succeeds) implements ModelInterface {
            public function __construct(private string $id, private bool $succeeds) {}

            public function name(): string
            {
                return $this->id;
            }

            public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
            {
                return \React\Promise\resolve($this->succeeds
                    ? new ModelResult($this->id, "{$this->id} answer", 0.01, true)
                    : new ModelResult($this->id, '', 0.01, false, "{$this->id} failed"));
            }
        };
    }

    /** @param array<string, mixed> $body */
    private function chatRequest(array $body): ServerRequest
    {
        return new ServerRequest('POST', 'http://localhost/v1/chat/completions', ['Content-Type' => 'application/json'], json_encode($body));
    }
}
