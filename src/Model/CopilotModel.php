<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Model;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Runs the Copilot CLI headlessly. The prompt is passed on stdin and output is captured through temp files
 * because ReactPHP process pipes are unavailable on Windows.
 */
final class CopilotModel implements ModelInterface
{
    private const POLL_INTERVAL = 0.2;

    public function __construct(
        private readonly string $command,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'copilot';
    }

    public function complete(string $prompt, string $systemPrompt = '', ?string $workdir = null): PromiseInterface
    {
        $started = microtime(true);
        $input = $systemPrompt !== '' ? $systemPrompt . "\n\n" . $prompt : $prompt;
        $workingDirectory = $workdir !== null && is_dir($workdir) ? $workdir : getcwd();

        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-router-copilot-' . bin2hex(random_bytes(6));
        [$inPath, $outPath, $errPath] = ["{$base}.in", "{$base}.out", "{$base}.err"];
        $cleanup = static function () use ($inPath, $outPath, $errPath): void {
            foreach ([$inPath, $outPath, $errPath] as $path) {
                @unlink($path);
            }
        };
        $fail = static fn(string $error): PromiseInterface => \React\Promise\resolve(
            new ModelResult('copilot', '', microtime(true) - $started, false, $error),
        );

        if (@file_put_contents($inPath, $input) === false) {
            $cleanup();
            return $fail('Unable to write the Copilot prompt file.');
        }

        $process = @proc_open(
            [$this->command, '-s'],
            [0 => ['file', $inPath, 'r'], 1 => ['file', $outPath, 'w'], 2 => ['file', $errPath, 'w']],
            $pipes,
            $workingDirectory ?: null,
        );
        if (!is_resource($process)) {
            $cleanup();
            return $fail("Unable to start '{$this->command}'.");
        }

        $deferred = new Deferred();
        $timer = null;
        $timer = Loop::addPeriodicTimer(self::POLL_INTERVAL, function () use (&$timer, $process, $started, $deferred, $outPath, $errPath, $cleanup): void {
            $status = proc_get_status($process);
            $timedOut = $status['running'] && (microtime(true) - $started) >= $this->timeout;
            if ($status['running'] && !$timedOut) {
                return;
            }

            Loop::cancelTimer($timer);
            $elapsed = microtime(true) - $started;
            if ($timedOut) {
                proc_terminate($process);
                proc_close($process);
                $cleanup();
                $deferred->resolve(new ModelResult('copilot', '', $elapsed, false, 'Copilot timed out.'));
                return;
            }

            $exitCode = $status['exitcode'];
            proc_close($process);
            $content = trim((string) @file_get_contents($outPath));
            $stderr = trim((string) @file_get_contents($errPath));
            $cleanup();

            if ($exitCode !== 0) {
                $deferred->resolve(new ModelResult('copilot', '', $elapsed, false, $stderr ?: "Copilot exited with code {$exitCode}."));
                return;
            }
            $deferred->resolve(new ModelResult('copilot', $content, $elapsed, $content !== '', $content === '' ? 'Copilot returned no output.' : null));
        });

        return $deferred->promise();
    }
}
