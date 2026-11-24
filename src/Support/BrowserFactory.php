<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Support;

use InvalidArgumentException;
use React\Dns\Config\Config;
use React\Http\Browser;
use React\Socket\Connector;

final class BrowserFactory
{
    public static function create(): Browser
    {
        $override = trim(Env::string('ROUTER_DNS_SERVER') ?? '');
        if ($override !== '') {
            if (filter_var($override, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('ROUTER_DNS_SERVER must be an IPv4 or IPv6 address.');
            }

            return new Browser(new Connector(['dns' => $override]));
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return new Browser();
        }

        // React Socket's default asks react/dns to run WMIC at startup on Windows.
        $output = function_exists('shell_exec')
            ? @shell_exec('powershell.exe -NoProfile -NonInteractive -Command "Get-DnsClientServerAddress | Select-Object -ExpandProperty ServerAddresses" 2>NUL')
            : null;
        $config = self::windowsDnsConfig(is_string($output) ? $output : null);

        return new Browser(new Connector(['dns' => $config]));
    }

    public static function windowsDnsConfig(?string $output): Config
    {
        $config = new Config();
        foreach (preg_split('/\R/', $output ?? '') as $line) {
            $address = trim($line);
            // A scoped server cannot be represented as an unscoped React DNS endpoint.
            if (filter_var($address, FILTER_VALIDATE_IP) === false || str_starts_with(strtolower($address), 'fec0:')) {
                continue;
            }

            if (!in_array($address, $config->nameservers, true)) {
                $config->nameservers[] = $address;
            }
        }

        if ($config->nameservers === []) {
            // Preserve React Socket's existing fallback when system discovery is unavailable.
            $config->nameservers[] = '8.8.8.8';
        }

        return $config;
    }
}
