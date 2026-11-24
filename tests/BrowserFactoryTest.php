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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBuildsBrowserWithoutRequiringWmicOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !function_exists('shell_exec')) {
            self::markTestSkipped('Windows DNS discovery requires a Windows PHP CLI with shell_exec.');
        }

        require __DIR__ . '/fixtures/windows-dns-shell.php';

        $key = 'ROUTER_DNS_SERVER';
        unset($_ENV[$key], $_SERVER[$key]);

        self::assertInstanceOf(Browser::class, BrowserFactory::create());
        self::assertSame(1, $GLOBALS['ai_router_test_dns_discoveries'] ?? 0);

        $_ENV[$key] = '192.0.2.53';
        self::assertInstanceOf(Browser::class, BrowserFactory::create());
        self::assertSame(1, $GLOBALS['ai_router_test_dns_discoveries']);

        // The old Router construction cannot pass even if the host still has WMIC.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WMIC discovery was attempted');
        new Browser();
    }
}
