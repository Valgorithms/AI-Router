<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Support\HttpClient;

final class JevClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
        private readonly HttpClient $http,
    ) {}

    /** @return PromiseInterface<array{choice:string,confidence:float,probabilities:array<string,float>}> */
    public function choose(string $state, array $criteria): PromiseInterface
    {
        return $this->request([
            'route' => [
                'type' => 'choice',
                'instructions' => 'Choose the execution tier. Prefer local for routine or bounded tasks. Choose cloud only when complexity, ambiguity, context, or task-specific tooling makes local execution less appropriate.',
                'criteria' => $criteria,
            ],
        ], $state, 'route');
    }

    /** @return PromiseInterface<array{choice:string,confidence:float,probabilities:array<string,float>}> */
    public function evaluate(string $state): PromiseInterface
    {
        return $this->request([
            'result' => [
                'type' => 'choice',
                'instructions' => 'Determine whether the attempted response adequately fulfills the task. Choose success only if it is adequate; otherwise choose escalate.',
                'criteria' => [
                    'success' => 'The response adequately fulfills the requested task.',
                    'escalate' => 'The response materially fails, is incomplete, unsupported, or needs another model.',
                ],
            ],
        ], $state, 'result');
    }

    private function request(array $questions, string $state, string $answerKey): PromiseInterface
    {
        return $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/systemone',
            ['Authorization' => 'Bearer ' . $this->apiKey],
            ['model' => $this->model, 'state' => $state, 'questions' => $questions],
            $this->timeout,
        )->then(function (array $response) use ($answerKey): array {
            $answer = $response['answers'][$answerKey] ?? null;
            if (!is_array($answer) || !isset($answer['choice'])) {
                throw new \RuntimeException("Jev response did not contain '{$answerKey}'.");
            }
            return [
                'choice' => (string) $answer['choice'],
                'confidence' => (float) ($answer['confidence'] ?? 0.0),
                'probabilities' => array_map('floatval', $answer['probabilities'] ?? []),
            ];
        });
    }
}
