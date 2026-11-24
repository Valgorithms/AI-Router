<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use React\Http\Browser;
use VzgCoders\AiRouter\Support\BrowserFactory;

final class BrowserFactoryTest extends TestCase
{
    public function testUsesOnlyDistinctUsableWindowsNameservers(): void
    {
        $output = "192.0.2.53\r\n2001:db8::53\r\ninvalid\r\n192.0.2.53\r\nfe80::1%4\r\nfec0:0:0:ffff::1";
        self::assertSame(['192.0.2.53', '2001:db8::53'], BrowserFactory::windowsDnsConfig($output)->nameservers);
        self::assertSame(['8.8.8.8'], BrowserFactory::windowsDnsConfig(null)->nameservers);
    }

    public function testRejectsInvalidOverride(): void
    {
        $key = 'ROUTER_DNS_SERVER';
        $oldEnv = $_ENV;
        try {
            $_ENV[$key] = 'resolver.example.invalid';
            $this->expectException(InvalidArgumentException::class);
            BrowserFactory::create();
        } finally {
            $_ENV = $oldEnv;
        }
    }

        public function testBuildsBrowserWithoutRequiringWmicOnWindows(): void
    {
        $key = 'ROUTER_DNS_SERVER';
        $oldEnv = $_ENV;
        $oldServer = $_SERVER;

        try {
            unset($_ENV[$key], $_SERVER[$key]);
            if (PHP_OS_FAMILY === 'Windows') {
                set_error_handler(static function (int $severity, string $message): bool {
                    if ($severity === E_DEPRECATED && str_contains($message, 'preg_match_all')) {
                        self::fail('Browser construction attempted WMIC-backed DNS discovery.');
                    }

                    return false;
                });
            }

            try {
                self::assertInstanceOf(Browser::class, BrowserFactory::create());
            } finally {
                if (PHP_OS_FAMILY === 'Windows') {
                    restore_error_handler();
                }
            }
        } finally {
            $_ENV = $oldEnv;
            $_SERVER = $oldServer;
        }
    }
}
