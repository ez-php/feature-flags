<?php

declare(strict_types=1);

namespace Tests\FeatureFlags\Driver;

use EzPhp\FeatureFlags\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Redis;
use Tests\TestCase;

/**
 * Class RedisDriverTest
 *
 * Requires a live Redis instance (available via Docker).
 * Tests are skipped automatically when ext-redis is not loaded.
 *
 * Uses Redis database 3 to avoid colliding with other modules' Redis tests
 * (queue uses 1, rate-limiter uses 2).
 *
 * @package Tests\FeatureFlags\Driver
 */
#[CoversClass(RedisDriver::class)]
final class RedisDriverTest extends TestCase
{
    private RedisDriver $driver;

    private Redis $redis;

    private bool $redisConnected = false;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not available.');
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $this->redis = new Redis();

        try {
            $connected = @$this->redis->connect($host, $port);
        } catch (\RedisException) {
            $this->markTestSkipped("Redis is not available at {$host}:{$port}.");
        }

        if (!$connected) {
            $this->markTestSkipped("Redis is not available at {$host}:{$port}.");
        }

        $this->redisConnected = true;
        $this->redis->select(3);
        $this->redis->flushDB();

        $this->driver = new RedisDriver($this->redis);
    }

    protected function tearDown(): void
    {
        if ($this->redisConnected) {
            $this->redis->flushDB();
        }
    }

    public function test_enabled_returns_false_for_unknown_flag(): void
    {
        $this->assertFalse($this->driver->enabled('unknown'));
    }

    public function test_enabled_returns_true_for_flag_set_to_1(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '1');

        $this->assertTrue($this->driver->enabled('new-ui'));
    }

    public function test_enabled_returns_false_for_flag_set_to_0(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '0');

        $this->assertFalse($this->driver->enabled('new-ui'));
    }

    public function test_enabled_for_falls_back_to_global_when_no_override(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '1');

        $this->assertTrue($this->driver->enabledFor('new-ui', 42));
    }

    public function test_enabled_for_uses_context_override_when_present(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '0');
        $this->redis->hSet('feature_flags:contexts:new-ui', '42', '1');

        $this->assertTrue($this->driver->enabledFor('new-ui', 42));
        $this->assertFalse($this->driver->enabledFor('new-ui', 99));
    }

    public function test_enabled_for_context_override_can_disable_a_globally_enabled_flag(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '1');
        $this->redis->hSet('feature_flags:contexts:new-ui', '42', '0');

        $this->assertFalse($this->driver->enabledFor('new-ui', 42));
    }

    public function test_all_returns_every_global_flag(): void
    {
        $this->redis->hSet('feature_flags', 'new-ui', '1');
        $this->redis->hSet('feature_flags', 'legacy-checkout', '0');

        $this->assertSame(
            ['new-ui' => true, 'legacy-checkout' => false],
            $this->driver->all(),
        );
    }

    public function test_all_returns_empty_array_when_no_flags(): void
    {
        $this->assertSame([], $this->driver->all());
    }
}
