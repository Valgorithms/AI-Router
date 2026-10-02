<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter;

use React\Filesystem\Filesystem;
use React\Http\Browser;
use VzgCoders\AiRouter\Evaluation\Evaluator;
use VzgCoders\AiRouter\Metrics\MetricsStore;
use VzgCoders\AiRouter\Model\ClaudeModel;
use VzgCoders\AiRouter\Model\CopilotModel;
use VzgCoders\AiRouter\Model\OllamaModel;
use VzgCoders\AiRouter\Support\Env;
use VzgCoders\AiRouter\Support\HttpClient;

final class Application
{
    public static function create(string $root): Router
    {
        Env::load($root);
        $browser = new Browser();
        $http = new HttpClient($browser);

        $ollama = new OllamaModel(
            Env::string('OLLAMA_URL', 'http://127.0.0.1:11434/v1'),
            Env::string('OLLAMA_MODEL', 'gemma4-agent-32k'),
            Env::int('OLLAMA_NUM_CTX', 32768),
            Env::int('OLLAMA_TIMEOUT', 120),
            Env::bool('OLLAMA_THINK', false),
            $http,
        );

        $claude = new ClaudeModel(
            Env::required('ANTHROPIC_API_KEY'),
            Env::string('ANTHROPIC_URL', 'https://api.anthropic.com'),
            Env::string('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
            Env::int('ANTHROPIC_MAX_TOKENS', 8192),
            Env::int('ANTHROPIC_TIMEOUT', 120),
            $http,
        );

        $copilot = new CopilotModel(
            Env::string('COPILOT_COMMAND', 'copilot'),
            Env::int('COPILOT_TIMEOUT', 300),
        );

        $jev = null;
        $jevKey = Env::string('TYPESAFE_API_KEY');
        if ($jevKey !== null && $jevKey !== '') {
            $jev = new JevClient(
                $jevKey,
                Env::string('JEV_URL', 'https://api.system-one.dev/v1'),
                Env::string('JEV_MODEL', 'jev'),
                Env::int('JEV_TIMEOUT', 30),
                $http,
            );
        }

        $metrics = new MetricsStore(
            Filesystem::createFromLoop(\React\EventLoop\Loop::get()),
            Env::string('METRICS_FILE', $root . '/var/metrics.jsonl'),
        );

        return new Router(
            ['local' => $ollama, 'claude' => $claude, 'copilot' => $copilot],
            $jev,
            new Evaluator($jev),
            $metrics,
            Env::bool('LOCAL_FIRST', true),
            (float) Env::string('CLOUD_CONFIDENCE_THRESHOLD', '0.80'),
            Env::int('MAX_ATTEMPTS', 3),
        );
    }
}
