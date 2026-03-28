<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\FlagManager;

/**
 * @covers \EzPhp\FeatureFlags\FlagManager
 */
final class FlagManagerTest extends TestCase
{
    public function testEnabledDelegatesToDriver(): void
    {
        $manager = new FlagManager(new ArrayDriver(['checkout' => true]));

        self::assertTrue($manager->enabled('checkout'));
        self::assertFalse($manager->enabled('other'));
    }

    public function testDisabledNegatesEnabled(): void
    {
        $manager = new FlagManager(new ArrayDriver(['checkout' => true, 'beta' => false]));

        self::assertFalse($manager->disabled('checkout'));
        self::assertTrue($manager->disabled('beta'));
        self::assertTrue($manager->disabled('unknown'));
    }

    public function testAllDelegatesToDriver(): void
    {
        $flags = ['feature-a' => true, 'feature-b' => false];
        $manager = new FlagManager(new ArrayDriver($flags));

        self::assertSame($flags, $manager->all());
    }

    public function testEnabledForDelegatesToDriver(): void
    {
        $manager = new FlagManager(new ArrayDriver(['checkout' => true]));

        self::assertTrue($manager->enabledFor('checkout', 1));
        self::assertFalse($manager->enabledFor('unknown', 1));
    }

    public function testDisabledForNegatesEnabledFor(): void
    {
        $manager = new FlagManager(new ArrayDriver(['checkout' => true, 'beta' => false]));

        self::assertFalse($manager->disabledFor('checkout', 42));
        self::assertTrue($manager->disabledFor('beta', 42));
        self::assertTrue($manager->disabledFor('unknown', 42));
    }
}
