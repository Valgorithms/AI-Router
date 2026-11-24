<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Support {
    function shell_exec(string $command): ?string
    {
        if (!str_contains($command, 'Get-DnsClientServerAddress')) {
            throw new \RuntimeException('Expected PowerShell DNS discovery');
        }

        $GLOBALS['ai_router_test_dns_discoveries'] = ($GLOBALS['ai_router_test_dns_discoveries'] ?? 0) + 1;

        return "192.0.2.53\r\n";
    }
}

namespace React\Dns\Config {
    function shell_exec(string $command): ?string
    {
        throw new \RuntimeException('WMIC discovery was attempted');
    }
}
