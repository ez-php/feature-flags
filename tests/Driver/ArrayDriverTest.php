<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use Tests\TestCase;

/**
 * @covers \EzPhp\FeatureFlags\Driver\ArrayDriver
 */
final class ArrayDriverTest extends TestCase
{
    public function testEnabledReturnsTrueForEnabledFlag(): void
    {
        $driver = new ArrayDriver(['feature-x' => true]);

        self::assertTrue($driver->enabled('feature-x'));
    }

    public function testEnabledReturnsFalseForDisabledFlag(): void
    {
        $driver = new ArrayDriver(['feature-x' => false]);

        self::assertFalse($driver->enabled('feature-x'));
    }

    public function testEnabledReturnsFalseForUnknownFlag(): void
    {
        $driver = new ArrayDriver([]);

        self::assertFalse($driver->enabled('unknown'));
    }

    public function testAllReturnsAllFlags(): void
    {
        $flags = ['feature-a' => true, 'feature-b' => false];
        $driver = new ArrayDriver($flags);

        self::assertSame($flags, $driver->all());
    }

    public function testAllReturnsEmptyArrayWhenNoFlags(): void
    {
        $driver = new ArrayDriver([]);

        self::assertSame([], $driver->all());
    }

    public function testEnabledForDelegatesToEnabledWhenFlagIsEnabled(): void
    {
        $driver = new ArrayDriver(['feature-x' => true]);

        self::assertTrue($driver->enabledFor('feature-x', 42));
    }

    public function testEnabledForDelegatesToEnabledWhenFlagIsDisabled(): void
    {
        $driver = new ArrayDriver(['feature-x' => false]);

        self::assertFalse($driver->enabledFor('feature-x', 42));
    }

    public function testEnabledForReturnsFalseForUnknownFlag(): void
    {
        $driver = new ArrayDriver([]);

        self::assertFalse($driver->enabledFor('unknown', 'user-1'));
    }
}
