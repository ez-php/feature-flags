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

        $this->pdo->exec(
            'CREATE TABLE feature_flag_contexts (
                name       VARCHAR(255) NOT NULL,
                context_id VARCHAR(255) NOT NULL,
                enabled    INTEGER      NOT NULL DEFAULT 0,
                PRIMARY KEY (name, context_id)
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

    private function insertContext(string $name, string $contextId, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO feature_flag_contexts (name, context_id, enabled) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $contextId, (int) $enabled]);
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

    public function testEnabledForReturnsContextOverrideWhenPresent(): void
    {
        $this->insert('checkout', false);
        $this->insertContext('checkout', '42', true);

        $driver = new DatabaseDriver($this->pdo);

        self::assertTrue($driver->enabledFor('checkout', 42));
    }

    public function testEnabledForReturnsContextOverrideForStringContextId(): void
    {
        $this->insert('checkout', false);
        $this->insertContext('checkout', 'user-abc', true);

        $driver = new DatabaseDriver($this->pdo);

        self::assertTrue($driver->enabledFor('checkout', 'user-abc'));
    }

    public function testEnabledForFallsBackToGlobalWhenNoContextOverride(): void
    {
        $this->insert('checkout', true);

        $driver = new DatabaseDriver($this->pdo);

        self::assertTrue($driver->enabledFor('checkout', 99));
    }

    public function testEnabledForContextOverrideCanDisableGloballyEnabledFlag(): void
    {
        $this->insert('checkout', true);
        $this->insertContext('checkout', '5', false);

        $driver = new DatabaseDriver($this->pdo);

        self::assertFalse($driver->enabledFor('checkout', 5));
        self::assertTrue($driver->enabledFor('checkout', 6));
    }

    public function testEnabledForFallsBackToGlobalWhenContextsTableMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE feature_flags (
                name    VARCHAR(255) NOT NULL PRIMARY KEY,
                enabled INTEGER      NOT NULL DEFAULT 0
            )'
        );
        $stmt = $pdo->prepare('INSERT INTO feature_flags (name, enabled) VALUES (?, ?)');
        $stmt->execute(['checkout', 1]);

        $driver = new DatabaseDriver($pdo);

        self::assertTrue($driver->enabledFor('checkout', 1));
    }

    public function testEnabledForReturnsFalseForUnknownFlagWithNoContext(): void
    {
        $driver = new DatabaseDriver($this->pdo);

        self::assertFalse($driver->enabledFor('unknown', 1));
    }
}
