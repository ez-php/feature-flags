<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\Flag;
use EzPhp\FeatureFlags\FlagManager;
use RuntimeException;

/**
 * @covers \EzPhp\FeatureFlags\Flag
 */
final class FlagTest extends TestCase
{
    protected function tearDown(): void
    {
        Flag::resetManager();
    }

    public function testEnabledReturnsTrueForEnabledFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['checkout' => true])));

        self::assertTrue(Flag::enabled('checkout'));
    }

    public function testEnabledReturnsFalseForDisabledFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['dark-mode' => false])));

        self::assertFalse(Flag::enabled('dark-mode'));
    }

    public function testEnabledReturnsFalseForUnknownFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver([])));

        self::assertFalse(Flag::enabled('unknown'));
    }

    public function testDisabledReturnsTrueForDisabledFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['feature' => false])));

        self::assertTrue(Flag::disabled('feature'));
    }

    public function testDisabledReturnsTrueForUnknownFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver([])));

        self::assertTrue(Flag::disabled('unknown'));
    }

    public function testAllReturnsAllFlags(): void
    {
        $flags = ['feature-a' => true, 'feature-b' => false];
        Flag::setManager(new FlagManager(new ArrayDriver($flags)));

        self::assertSame($flags, Flag::all());
    }

    public function testThrowsRuntimeExceptionWhenNotInitialised(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Flag facade is not initialised');

        Flag::enabled('anything');
    }

    public function testResetManagerClearsTheSingleton(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['f' => true])));
        Flag::resetManager();

        $this->expectException(RuntimeException::class);

        Flag::enabled('f');
    }

    public function testEnabledForReturnsTrueWhenFlagEnabled(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['checkout' => true])));

        self::assertTrue(Flag::enabledFor('checkout', 42));
    }

    public function testEnabledForReturnsFalseForUnknownFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver([])));

        self::assertFalse(Flag::enabledFor('unknown', 1));
    }

    public function testDisabledForReturnsTrueForDisabledFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['feature' => false])));

        self::assertTrue(Flag::disabledFor('feature', 'user-1'));
    }

    public function testDisabledForReturnsFalseForEnabledFlag(): void
    {
        Flag::setManager(new FlagManager(new ArrayDriver(['feature' => true])));

        self::assertFalse(Flag::disabledFor('feature', 'user-1'));
    }
}
