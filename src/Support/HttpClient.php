<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Support;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;

final class HttpClient
{
    public function __construct(private readonly Browser $browser) {}

    /** @return PromiseInterface<array<string,mixed>> */
    public function postJson(string $url, array $headers, array $body, int $timeout): PromiseInterface
    {
        $requestHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        return $this->browser
            ->withTimeout($timeout)
            ->post($url, $requestHeaders, json_encode($body, JSON_THROW_ON_ERROR))
            ->then(function (ResponseInterface $response): array {
                $status = $response->getStatusCode();
                $raw = $response->getBody()->getContents();
                $decoded = json_decode($raw, true);
                if ($status < 200 || $status >= 300) {
                    throw new RuntimeException("HTTP {$status}: {$raw}");
                }
                if (!is_array($decoded)) {
                    throw new RuntimeException('HTTP response was not valid JSON.');
                }
                return $decoded;
            });
    }
}
