<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestingDatabaseGuard
{
    public static function assertApplicationIsSafe(Application $app): void
    {
        $connection = (string) $app['config']->get('database.default');
        $driver = (string) $app['config']->get("database.connections.{$connection}.driver");
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        self::assertSafe((string) $app->environment(), $driver, $database, $app->basePath());
    }

    public static function assertSafe(string $environment, string $driver, string $database, string $basePath): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('Test database guard: APP_ENV must be testing.');
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Test database guard: only an isolated SQLite connection is allowed.');
        }
        if ($database === ':memory:') {
            return;
        }
        if ($database === '') {
            throw new RuntimeException('Test database guard: DB_DATABASE must be explicit.');
        }

        $base = self::normalize($basePath);
        $path = self::normalize(self::isAbsolute($database) ? $database : $basePath.DIRECTORY_SEPARATOR.$database);
        $development = self::normalize($basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite');
        $allowedDatabase = self::normalize($basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'testing.sqlite');
        $allowedRuntime = self::normalize($basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing').'/';

        if ($path === $development) {
            throw new RuntimeException('Test database guard: development database.sqlite is forbidden.');
        }
        if ($path !== $allowedDatabase && ! str_starts_with($path.'/', $allowedRuntime)) {
            throw new RuntimeException('Test database guard: SQLite path is outside the allowed testing locations.');
        }
        if (! str_starts_with($path, $base.'/')) {
            throw new RuntimeException('Test database guard: SQLite path must remain inside the project.');
        }
    }

    private static function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;

        return PHP_OS_FAMILY === 'Windows' ? strtolower(rtrim($normalized, '/')) : rtrim($normalized, '/');
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
