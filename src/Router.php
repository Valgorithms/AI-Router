<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter;

use React\Promise\PromiseInterface;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\Metrics\MetricsStore;
use VzgCoders\AiRouter\Model\ModelInterface;
use VzgCoders\AiRouter\Model\ModelResult;

final class Router
{
    /** @param array<string, ModelInterface> $models */
    public function __construct(
        private readonly array $models,
        private readonly ?JevClient $jev,
        private readonly Evaluator $evaluator,
        private readonly MetricsStore $metrics,
        private readonly bool $localFirst,
        private readonly float $cloudConfidenceThreshold,
        private readonly int $maxAttempts,
    ) {}

    /** @return PromiseInterface<array{result:ModelResult,evaluations:list<array<string,mixed>>}> */
    public function run(string $prompt, ?string $workdir = null): PromiseInterface
    {
        return $this->initialOrder($prompt)->then(fn(array $order) => $this->attempt($prompt, $workdir, $order, 0, []));
    }

    private function attempt(string $prompt, ?string $workdir, array $order, int $index, array $evaluations): PromiseInterface
    {
        // The local model is always reachable as a fallback, even when the attempt cap would otherwise skip it.
        $localPosition = array_search('local', $order, true);
        $limit = is_int($localPosition) ? max($this->maxAttempts, $localPosition + 1) : $this->maxAttempts;
        if ($index >= count($order) || $index >= $limit) {
            $last = $evaluations[count($evaluations) - 1] ?? null;
            $model = is_array($last) ? (string) ($last['model'] ?? 'none') : 'none';
            return \React\Promise\resolve([
                'result' => new ModelResult($model, '', 0.0, false, 'All available model attempts failed evaluation.'),
                'evaluations' => $evaluations,
            ]);
        }

        $name = $order[$index];
        $model = $this->models[$name] ?? null;
        if ($model === null) {
            return $this->attempt($prompt, $workdir, $order, $index + 1, $evaluations);
        }

        return $model->complete($prompt, $this->systemPrompt($workdir), $workdir)
            ->then(fn(ModelResult $result) => $this->evaluator->evaluate($prompt, $result, $workdir)
                ->then(function ($evaluation) use ($prompt, $workdir, $order, $index, $evaluations, $result): PromiseInterface {
                    $entry = [
                        'model' => $result->model,
                        'success' => $evaluation->success,
                        'reason' => $evaluation->reason,
                        'confidence' => $evaluation->confidence,
                        'latency_seconds' => $result->latencySeconds,
                    ];
                    $nextEvaluations = [...$evaluations, $entry];
                    return $this->metrics->record($prompt, $result, $evaluation)
                        ->then(function () use ($evaluation, $result, $nextEvaluations, $prompt, $workdir, $order, $index): PromiseInterface|array {
                            if ($evaluation->success) {
                                return ['result' => $result, 'evaluations' => $nextEvaluations];
                            }
                            return $this->attempt($prompt, $workdir, $order, $index + 1, $nextEvaluations);
                        });
                }));
    }

    /** @return PromiseInterface<list<string>> */
    private function initialOrder(string $prompt): PromiseInterface
    {
        $default = ['local', 'claude', 'copilot'];
        if (!$this->localFirst || $this->jev === null) {
            return \React\Promise\resolve($default);
        }

        return $this->jev->choose($prompt, [
            'local' => 'Preferred default. Use for routine coding, explanation, documentation, bounded debugging, and tasks with sufficient context.',
            'claude' => 'Use first when the task is complex, ambiguous, reasoning-heavy, or likely to exceed local capability.',
            'copilot' => 'Use first only when GitHub-native repository context or Copilot-specific tooling is particularly useful.',
        ])->then(function (array $decision) use ($default): array {
            $choice = $decision['choice'];
            if ($decision['confidence'] >= $this->cloudConfidenceThreshold && in_array($choice, ['claude', 'copilot'], true)) {
                return array_values(array_unique([$choice, ...$default]));
            }
            return $default;
        }, fn() => $default);
    }

    private function systemPrompt(?string $workdir): string
    {
        $context = $workdir !== null ? "The working directory is {$workdir}." : '';
        return trim("You are an implementation-focused coding assistant. Be precise and do not claim tests passed unless they actually did. {$context}");
    }
}
