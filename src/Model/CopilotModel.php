<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\ChildProcess\Process;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\EventLoop\Loop;

final class CopilotModel implements ModelInterface
{
    public function __construct(
        private readonly string $command,
        private readonly int $timeout,
    ) {}

    public function name(): string { return 'copilot'; }

    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
    {
        $started = microtime(true);
        $deferred = new Deferred();
        $workingDirectory = $workdir !== null && is_dir($workdir) ? $workdir : getcwd();
        $input = $systemPrompt !== '' ? $systemPrompt . "\n\n" . $prompt : $prompt;

        $process = new Process($this->command . ' -sp ' . escapeshellarg($input), $workingDirectory ?: null);
        $stdout = '';
        $stderr = '';
        $settled = false;
        $timer = Loop::addTimer($this->timeout, function () use (&$settled, $deferred, $process): void {
            if ($settled) {
                return;
            }
            $settled = true;
            $process->terminate();
            $deferred->resolve(new ModelResult('copilot', '', 0.0, false, 'Copilot timed out.'));
        });

        $process->start();
        $process->stdout?->on('data', function (string $chunk) use (&$stdout): void { $stdout .= $chunk; });
        $process->stderr?->on('data', function (string $chunk) use (&$stderr): void { $stderr .= $chunk; });
        $process->on('exit', function (?int $exitCode) use (&$settled, &$stdout, &$stderr, $deferred, $started, $timer): void {
            Loop::cancelTimer($timer);
            if ($settled) {
                return;
            }
            $settled = true;
            if ($exitCode !== 0) {
                $deferred->resolve(new ModelResult('copilot', '', microtime(true) - $started, false, trim($stderr) ?: "Copilot exited with code {$exitCode}."));
                return;
            }
            $content = trim($stdout);
            $deferred->resolve(new ModelResult('copilot', $content, microtime(true) - $started, $content !== '', $content === '' ? 'Copilot returned no output.' : null));
        });

        return $deferred->promise();
    }
}
