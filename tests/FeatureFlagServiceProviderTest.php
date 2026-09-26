<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\Driver\RedisDriver;
use EzPhp\FeatureFlags\Driver\RolloutDriver;
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
#[UsesClass(RedisDriver::class)]
#[UsesClass(RolloutDriver::class)]
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

    public function test_rollouts_from_config_enable_percentage_targeting(): void
    {
        $container = new FakeContainer(new FakeConfig([
            'flags.driver' => 'array',
            'flags.rollouts' => ['everyone' => 100, 'nobody' => 0, 'ignored' => 'fifty'],
        ]));
        $provider = new FeatureFlagServiceProvider($container);
        $provider->register();
        $provider->boot();

        $this->assertTrue(Flag::enabledFor('everyone', 'user-1'));
        $this->assertFalse(Flag::enabledFor('nobody', 'user-1'));
        $this->assertFalse(Flag::enabledFor('ignored', 'user-1'), 'non-integer percentages are ignored');
    }

    public function test_without_rollouts_the_driver_is_not_wrapped(): void
    {
        $container = new FakeContainer(new FakeConfig(['flags.driver' => 'array', 'flags.rollouts' => []]));
        $provider = new FeatureFlagServiceProvider($container);
        $provider->register();
        $provider->boot();

        $this->assertSame([], Flag::all());
    }

    public function test_register_binds_redis_driver_when_configured(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not available.');
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $redis = new \Redis();

        try {
            $connected = @$redis->connect($host, $port);
        } catch (\RedisException) {
            $this->markTestSkipped("Redis is not available at {$host}:{$port}.");
        }

        if (!$connected) {
            $this->markTestSkipped("Redis is not available at {$host}:{$port}.");
        }

        $redis->select(3);
        $redis->flushDB();
        // A value only RedisDriver's hash-based lookup can see — FileDriver
        // (the fallback branch this test must NOT be exercising) would read
        // from a PHP file and could never observe this.
        $redis->hSet('feature_flags', 'redis-only-flag', '1');

        $container = new FakeContainer(new FakeConfig([
            'flags.driver' => 'redis',
            'flags.redis.host' => $host,
            'flags.redis.port' => $port,
            'flags.redis.database' => 3,
        ]));
        $provider = new FeatureFlagServiceProvider($container);

        $provider->register();

        /** @var FlagManager $manager */
        $manager = $container->make(FlagManager::class);

        $this->assertTrue($manager->enabled('redis-only-flag'));

        $redis->flushDB();
    }
}
