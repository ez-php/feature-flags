<?php

declare(strict_types=1);

namespace Tests\FeatureFlags\Driver;

use EzPhp\Cache\ArrayDriver as CacheArrayDriver;
use EzPhp\FeatureFlags\Driver\CachedDriver;
use EzPhp\FeatureFlags\FlagDriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Counts how often the wrapped store is actually read.
 */
final class FlagsCountingDriver implements FlagDriverInterface
{
    public int $allCalls = 0;

    public int $enabledForCalls = 0;

    /**
     * @param array<string, bool>               $flags
     * @param array<string, array<array-key, bool>> $contexts
     */
    public function __construct(public array $flags, public array $contexts = [])
    {
    }

    public function enabled(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    public function enabledFor(string $name, int|string $contextId): bool
    {
        $this->enabledForCalls++;

        return $this->contexts[$name][(string) $contextId] ?? $this->enabled($name);
    }

    public function all(): array
    {
        $this->allCalls++;

        return $this->flags;
    }
}

#[CoversClass(CachedDriver::class)]
final class CachedDriverTest extends TestCase
{
    private int $now = 1_000_000;

    private function cached(FlagDriverInterface $driver, ?CacheArrayDriver $cache = null, int $ttl = 60): CachedDriver
    {
        return new CachedDriver($driver, $cache ?? new CacheArrayDriver(), $ttl, 'flags', fn (): int => $this->now);
    }

    public function testManyChecksReadTheStoreOnce(): void
    {
        $inner = new FlagsCountingDriver(['a' => true, 'b' => false]);
        $driver = $this->cached($inner);

        $results = [$driver->enabled('a'), $driver->enabled('b'), $driver->enabled('missing'), $driver->all()];

        self::assertSame([true, false, false, ['a' => true, 'b' => false]], $results);
        self::assertSame(1, $inner->allCalls);
    }

    public function testTheSnapshotIsSharedThroughTheCacheAcrossInstances(): void
    {
        $cache = new CacheArrayDriver();
        $inner = new FlagsCountingDriver(['a' => true]);

        $this->cached($inner, $cache)->enabled('a');
        $inner->flags['a'] = false;

        self::assertTrue($this->cached($inner, $cache)->enabled('a'));
        self::assertSame(1, $inner->allCalls);
    }

    public function testTheMemoExpiresWithTheTtlInLongRunningProcesses(): void
    {
        $inner = new FlagsCountingDriver(['a' => true]);
        $cache = new CacheArrayDriver();
        $driver = $this->cached($inner, $cache, 30);

        $driver->enabled('a');
        $inner->flags['a'] = false;
        $cache->flush(); // the shared entry expired too
        $this->now += 31;

        self::assertFalse($driver->enabled('a'));
        self::assertSame(2, $inner->allCalls);
    }

    public function testEnabledForIsCachedPerNameAndContext(): void
    {
        $inner = new FlagsCountingDriver(['beta' => false], ['beta' => ['7' => true]]);
        $driver = $this->cached($inner);

        $results = [
            $driver->enabledFor('beta', 7),
            $driver->enabledFor('beta', 7),
            $driver->enabledFor('beta', 8),
            $driver->enabledFor('beta', 8),
        ];

        self::assertSame([true, true, false, false], $results);
        self::assertSame(2, $inner->enabledForCalls);
    }

    public function testForgetInvalidatesEveryCachedAnswer(): void
    {
        $cache = new CacheArrayDriver();
        $inner = new FlagsCountingDriver(['a' => true], ['a' => ['1' => true]]);
        $driver = $this->cached($inner, $cache);

        $driver->enabled('a');
        $driver->enabledFor('a', 1);
        $inner->flags['a'] = false;
        $inner->contexts['a']['1'] = false;
        $driver->forget();

        // A second instance sharing the cache sees the change as well.
        $other = $this->cached($inner, $cache);

        self::assertSame([false, false, false], [$driver->enabled('a'), $driver->enabledFor('a', 1), $other->enabled('a')]);
    }

    public function testACorruptedCacheEntryIsReadFromTheDriverAgain(): void
    {
        $cache = new CacheArrayDriver();
        $cache->set('flags:v0:all', ['a' => 'yes']);
        $inner = new FlagsCountingDriver(['a' => true]);

        self::assertTrue($this->cached($inner, $cache)->enabled('a'));
        self::assertSame(1, $inner->allCalls);
    }

    public function testATtlBelowOneSecondIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CachedDriver(new FlagsCountingDriver([]), new CacheArrayDriver(), 0);
    }
}
