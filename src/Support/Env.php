<?php

declare(strict_types=1);

namespace VzgCoders\AiRouter\Support;

use Dotenv\Dotenv;

final class Env
{
    public static function load(string $directory): void
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR);
        if (is_file($path . DIRECTORY_SEPARATOR . '.env')) {
            Dotenv::createImmutable($path)->safeLoad();
        }
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        return $value === null ? null : (string) $value;
    }

    public static function required(string $key): string
    {
        $value = self::string($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required environment variable: {$key}");
        }
        return $value;
    }

    public static function int(string $key, int $default): int
    {
        return (int) (self::string($key, (string) $default));
    }

    public static function bool(string $key, bool $default): bool
    {
        return filter_var(self::string($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOL);
    }
}
