<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Async;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use VzgCoders\AiRouter\Model\OpenAiModel;
use VzgCoders\AiRouter\Support\HttpClient;

final class OpenAiModelTest extends TestCase
{
    public function testSendsBearerAuthAndParsesCompletion(): void
    {
        $captured = null;
        $socket = new SocketServer('127.0.0.1:0');
        $server = new HttpServer(function (ServerRequestInterface $request) use (&$captured): Response {
            $captured = [
                'path' => $request->getUri()->getPath(),
                'auth' => $request->getHeaderLine('Authorization'),
                'body' => json_decode((string) $request->getBody(), true),
            ];
            return Response::json(['choices' => [['message' => ['content' => 'hello']]]]);
        });
        $server->listen($socket);
        $port = parse_url($socket->getAddress(), PHP_URL_PORT);

        $model = new OpenAiModel('sk-test', "http://127.0.0.1:$port/v1", 'gpt-test', 100, 5, new HttpClient(new Browser()));
        $result = Async\await($model->complete('hi', 'be brief'));
        $socket->close();

        self::assertTrue($result->transportSuccess);
        self::assertSame('openai', $result->model);
        self::assertSame('hello', $result->content);
        self::assertIsArray($captured);
        self::assertSame('/v1/chat/completions', $captured['path']);
        self::assertSame('Bearer sk-test', $captured['auth']);
        self::assertSame('gpt-test', $captured['body']['model']);
        self::assertSame('system', $captured['body']['messages'][0]['role']);
    }

    public function testReportsFailureWhenUnreachable(): void
    {
        $model = new OpenAiModel('sk-test', 'http://127.0.0.1:1/v1', 'gpt-test', 100, 2, new HttpClient(new Browser()));
        $result = Async\await($model->complete('hi'));

        self::assertFalse($result->transportSuccess);
    }
}
