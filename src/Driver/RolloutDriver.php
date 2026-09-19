<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use EzPhp\FeatureFlags\FlagDriverInterface;

/**
 * Class RolloutDriver
 *
 * Decorator that adds percentage rollouts on top of any other driver.
 *
 * A rollout is a percentage (0–100) per flag name. For a flag with a rollout,
 * `enabledFor($name, $contextId)` is true for a stable slice of contexts:
 *
 *   crc32($name . '|' . $contextId) % 100 < $percent
 *
 * The bucket depends only on the flag name and the context id, so the same user
 * always gets the same answer, raising the percentage only ever adds users (a
 * user enabled at 10 % stays enabled at 25 %), and different flags roll out to
 * different slices.
 *
 * Precedence: a configured rollout **replaces** the wrapped driver's answer for
 * that flag. Flags without a rollout are delegated untouched. Without a context
 * there is no bucket to compute, so `enabled($name)` is true only for a 100 %
 * rollout — a partial rollout is not "on" globally.
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class RolloutDriver implements FlagDriverInterface
{
    /**
     * @var array<string, int<0, 100>>
     */
    private readonly array $rollouts;

    /**
     * RolloutDriver Constructor
     *
     * @param FlagDriverInterface $inner    Driver that answers for flags without a rollout.
     * @param array<string, int>  $rollouts Flag name → percentage; values are clamped to 0–100.
     */
    public function __construct(private readonly FlagDriverInterface $inner, array $rollouts)
    {
        $clamped = [];

        foreach ($rollouts as $name => $percent) {
            $clamped[$name] = max(0, min(100, $percent));
        }

        $this->rollouts = $clamped;
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        if (isset($this->rollouts[$name])) {
            return $this->rollouts[$name] >= 100;
        }

        return $this->inner->enabled($name);
    }

    /**
     * {@inheritDoc}
     */
    public function enabledFor(string $name, int|string $contextId): bool
    {
        if (isset($this->rollouts[$name])) {
            return self::bucket($name, $contextId) < $this->rollouts[$name];
        }

        return $this->inner->enabledFor($name, $contextId);
    }

    /**
     * {@inheritDoc}
     *
     * Flags with a rollout are reported by their global state (`enabled()`).
     */
    public function all(): array
    {
        $all = $this->inner->all();

        foreach ($this->rollouts as $name => $percent) {
            $all[$name] = $percent >= 100;
        }

        return $all;
    }

    /**
     * Stable bucket 0–99 for a flag/context pair.
     *
     * @param string     $name
     * @param int|string $contextId
     *
     * @return int
     */
    public static function bucket(string $name, int|string $contextId): int
    {
        return crc32($name . '|' . $contextId) % 100;
    }
}
