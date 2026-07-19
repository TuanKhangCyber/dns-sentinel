<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestingDatabaseGuard;

class TestingDatabaseGuardTest extends TestCase
{
    public function test_memory_and_explicit_testing_sqlite_are_allowed(): void
    {
        TestingDatabaseGuard::assertSafe('testing', 'sqlite', ':memory:', 'C:\\project');
        TestingDatabaseGuard::assertSafe('testing', 'sqlite', 'database/testing.sqlite', 'C:\\project');
        TestingDatabaseGuard::assertSafe('testing', 'sqlite', 'storage/framework/testing/queue.sqlite', 'C:\\project');

        $this->addToAssertionCount(3);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_configuration_fails_before_laravel_database_traits(
        string $environment,
        string $driver,
        string $database,
    ): void {
        $this->expectException(RuntimeException::class);
        TestingDatabaseGuard::assertSafe($environment, $driver, $database, 'C:\\project');
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'non-testing environment' => ['local', 'sqlite', ':memory:'],
            'production environment' => ['production', 'sqlite', ':memory:'],
            'non-sqlite connection' => ['testing', 'mysql', 'production'],
            'empty database' => ['testing', 'sqlite', ''],
            'development sqlite' => ['testing', 'sqlite', 'database/database.sqlite'],
            'outside project' => ['testing', 'sqlite', 'C:\\outside\\testing.sqlite'],
            'unapproved project path' => ['testing', 'sqlite', 'database/other.sqlite'],
        ];
    }
}
