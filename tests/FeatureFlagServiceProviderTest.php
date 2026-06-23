<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\FeatureFlagServiceProvider;
use EzPhp\FeatureFlags\Flag;
use EzPhp\FeatureFlags\FlagManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: FeatureFlagServiceProvider registers and boots its bindings in a
 * minimal container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(FeatureFlagServiceProvider::class)]
#[UsesClass(FlagManager::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(Flag::class)]
final class FeatureFlagServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Flag::resetManager();
        parent::tearDown();
    }

    public function test_register_binds_flag_manager(): void
    {
        $container = new FakeContainer(new FakeConfig(['flags.driver' => 'array']));
        $provider = new FeatureFlagServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->wasBound(FlagManager::class));
        $this->assertInstanceOf(FlagManager::class, $container->make(FlagManager::class));
    }

    public function test_boot_initialises_flag_facade(): void
    {
        $container = new FakeContainer(new FakeConfig(['flags.driver' => 'array']));
        $provider = new FeatureFlagServiceProvider($container);

        $provider->register();
        $provider->boot();

        // The facade is now usable without throwing.
        $this->assertSame([], Flag::all());
    }
}
