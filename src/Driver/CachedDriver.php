<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use Closure;
use EzPhp\Cache\CacheInterface;
use EzPhp\FeatureFlags\FlagDriverInterface;

/**
 * Class CachedDriver
 *
 * Read-through cache in front of any FlagDriverInterface, so a page checking N
 * flags no longer costs N round trips to the database or Redis.
 *
 * - `enabled()` and `all()` are answered from one `all()` snapshot of the inner
 *   driver, stored in the cache for `$ttl` seconds and memoised in this instance
 *   until the same TTL runs out (long-running workers re-read it).
 * - `enabledFor()` cannot be derived from the snapshot (drivers decide per
 *   context), so each name/context pair is cached and memoised on its own.
 *
 * The cache is never the primary store: an evicted entry is simply re-read from
 * the inner driver. Changes made directly in the store become visible after at
 * most `$ttl` seconds; call `forget()` after changing a flag to make them visible
 * at once. `forget()` bumps a version counter that is part of every key, so all
 * cached pairs are invalidated without enumerating them.
 *
 * Requires ez-php/cache (a `suggest` of this package).
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class CachedDriver implements FlagDriverInterface
{
    /**
     * @var array{flags: array<string, bool>, until: int}|null
     */
    private ?array $snapshot = null;

    /**
     * @var array<string, array{enabled: bool, until: int}>
     */
    private array $contextual = [];

    private readonly Closure $clock;

    /**
     * @param FlagDriverInterface   $driver The driver holding the flags.
     * @param CacheInterface        $cache  Shared store for the snapshot and per-context results.
     * @param int                   $ttl    Seconds a cached answer stays valid (≥ 1).
     * @param string                $prefix Cache key prefix.
     * @param (Closure(): int)|null $clock  Current Unix time; defaults to time() (tests inject one).
     *
     * @throws \InvalidArgumentException When $ttl is below 1.
     */
    public function __construct(
        private readonly FlagDriverInterface $driver,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 60,
        private readonly string $prefix = 'flags',
        ?Closure $clock = null,
    ) {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('CachedDriver needs a TTL of at least 1 second.');
        }

        $this->clock = $clock ?? time(...);
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        return $this->snapshot()[$name] ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function enabledFor(string $name, int|string $contextId): bool
    {
        $now = $this->now();
        $memoKey = $name . "\0" . $contextId;
        $memo = $this->contextual[$memoKey] ?? null;

        if ($memo !== null && $memo['until'] > $now) {
            return $memo['enabled'];
        }

        $key = $this->key('for:' . hash('xxh128', $memoKey));
        $cached = $this->cache->get($key);

        if (is_bool($cached)) {
            $enabled = $cached;
        } else {
            $enabled = $this->driver->enabledFor($name, $contextId);
            $this->cache->set($key, $enabled, $this->ttl);
        }

        $this->contextual[$memoKey] = ['enabled' => $enabled, 'until' => $now + $this->ttl];

        return $enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->snapshot();
    }

    /**
     * Drop every cached answer, here and in the shared store, so the next check
     * reads the inner driver again. Call it after changing a flag.
     *
     * @return void
     */
    public function forget(): void
    {
        $this->snapshot = null;
        $this->contextual = [];
        $this->cache->set($this->prefix . ':version', $this->version() + 1);
    }

    /**
     * The flag map: memoised, else from the cache, else from the inner driver.
     *
     * @return array<string, bool>
     */
    private function snapshot(): array
    {
        $now = $this->now();

        if ($this->snapshot !== null && $this->snapshot['until'] > $now) {
            return $this->snapshot['flags'];
        }

        $key = $this->key('all');
        $cached = $this->cache->get($key);
        $flags = is_array($cached) ? self::flagMap($cached) : null;

        if ($flags === null) {
            $flags = $this->driver->all();
            $this->cache->set($key, $flags, $this->ttl);
        }

        $this->snapshot = ['flags' => $flags, 'until' => $now + $this->ttl];

        return $flags;
    }

    /**
     * Keep a cached value only when it is a name → bool map, so a foreign or
     * corrupted entry is re-read from the driver instead of trusted.
     *
     * @param array<mixed> $value
     *
     * @return array<string, bool>|null
     */
    private static function flagMap(array $value): ?array
    {
        $flags = [];

        foreach ($value as $name => $enabled) {
            if (!is_string($name) || !is_bool($enabled)) {
                return null;
            }

            $flags[$name] = $enabled;
        }

        return $flags;
    }

    /**
     * @param string $suffix
     *
     * @return string
     */
    private function key(string $suffix): string
    {
        return $this->prefix . ':v' . $this->version() . ':' . $suffix;
    }

    /**
     * @return int
     */
    private function version(): int
    {
        $version = $this->cache->get($this->prefix . ':version', 0);

        return is_int($version) ? $version : 0;
    }

    /**
     * @return int
     */
    private function now(): int
    {
        return ($this->clock)();
    }
}
