<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\FeatureFlags\Driver\DatabaseDriver;
use PDO;
use Tests\TestCase;

/**
 * @covers \EzPhp\FeatureFlags\Driver\DatabaseDriver
 */
final class DatabaseDriverTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            'CREATE TABLE feature_flags (
                name    VARCHAR(255) NOT NULL PRIMARY KEY,
                enabled INTEGER      NOT NULL DEFAULT 0
            )'
        );
    }

    private function insert(string $name, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO feature_flags (name, enabled) VALUES (?, ?)'
        );
        $stmt->execute([$name, (int) $enabled]);
    }

    public function testEnabledReturnsTrueForEnabledFlag(): void
    {
        $this->insert('checkout', true);

        $driver = new DatabaseDriver($this->pdo);

        self::assertTrue($driver->enabled('checkout'));
    }

    public function testEnabledReturnsFalseForDisabledFlag(): void
    {
        $this->insert('dark-mode', false);

        $driver = new DatabaseDriver($this->pdo);

        self::assertFalse($driver->enabled('dark-mode'));
    }

    public function testEnabledReturnsFalseForUnknownFlag(): void
    {
        $driver = new DatabaseDriver($this->pdo);

        self::assertFalse($driver->enabled('unknown'));
    }

    public function testEnabledReturnsFalseWhenTableMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = new DatabaseDriver($pdo);

        self::assertFalse($driver->enabled('any-flag'));
    }

    public function testAllReturnsAllFlags(): void
    {
        $this->insert('feature-a', true);
        $this->insert('feature-b', false);

        $driver = new DatabaseDriver($this->pdo);
        $result = $driver->all();

        self::assertSame(['feature-a' => true, 'feature-b' => false], $result);
    }

    public function testAllReturnsEmptyArrayWhenNoRows(): void
    {
        $driver = new DatabaseDriver($this->pdo);

        self::assertSame([], $driver->all());
    }

    public function testAllReturnsEmptyArrayWhenTableMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = new DatabaseDriver($pdo);

        self::assertSame([], $driver->all());
    }
}
