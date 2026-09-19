<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use EzPhp\FeatureFlags\FlagDriverInterface;
use Redis;
use RuntimeException;

/**
 * Class RedisDriver
 *
 * Reads feature flags from Redis hashes — same schema shape as DatabaseDriver,
 * mapped onto hashes instead of tables:
 *
 *   HSET feature_flags <name> 1|0                          — global flag state
 *   HSET feature_flags:contexts:<name> <contextId> 1|0     — optional per-context override
 *
 * enabledFor() checks the per-flag context hash first and falls back to the
 * global feature_flags value when no override exists — same fallback order
 * as DatabaseDriver's feature_flag_contexts → feature_flags lookup.
 *
 * Requires the PHP `ext-redis` extension. Read-only, same contract as
 * ArrayDriver/FileDriver/DatabaseDriver — this module has no flag-writing API.
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class RedisDriver implements FlagDriverInterface
{
    /**
     * RedisDriver Constructor
     *
     * @param Redis $redis Already-connected Redis instance.
     *
     * @throws RuntimeException When ext-redis is not loaded.
     */
    public function __construct(private readonly Redis $redis)
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('The ext-redis extension is required to use RedisDriver.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        $value = $this->redis->hGet('feature_flags', $name);

        return is_string($value) && (bool) (int) $value;
    }

    /**
     * {@inheritDoc}
     */
    public function enabledFor(string $name, int|string $contextId): bool
    {
        $value = $this->redis->hGet('feature_flags:contexts:' . $name, (string) $contextId);

        if (is_string($value)) {
            return (bool) (int) $value;
        }

        return $this->enabled($name);
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        $rows = $this->redis->hGetAll('feature_flags');

        if (!is_array($rows)) {
            return [];
        }

        $flags = [];

        foreach ($rows as $name => $value) {
            if (is_string($name)) {
                $flags[$name] = (bool) (int) $value;
            }
        }

        return $flags;
    }
}
