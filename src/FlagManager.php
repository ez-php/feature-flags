<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags;

/**
 * Class FlagManager
 *
 * Core evaluator that delegates flag lookups to the configured driver.
 * Held as a singleton by the `Flag` facade and initialised by
 * `FeatureFlagServiceProvider::boot()`.
 *
 * @package EzPhp\FeatureFlags
 */
final class FlagManager
{
    /**
     * @param FlagDriverInterface $driver Storage backend.
     */
    public function __construct(private readonly FlagDriverInterface $driver)
    {
    }

    /**
     * Returns true when the named flag is enabled.
     * Returns false for unknown flags — never throws.
     */
    public function enabled(string $name): bool
    {
        return $this->driver->enabled($name);
    }

    /**
     * Returns true when the named flag is enabled for the given context.
     * Falls back to the global enabled() state when no context-specific override exists.
     *
     * @param int|string $contextId Identifier for the evaluation context (e.g. user ID).
     */
    public function enabledFor(string $name, int|string $contextId): bool
    {
        return $this->driver->enabledFor($name, $contextId);
    }

    /**
     * Returns true when the named flag is disabled or unknown.
     */
    public function disabled(string $name): bool
    {
        return !$this->driver->enabled($name);
    }

    /**
     * Returns true when the named flag is disabled or unknown for the given context.
     *
     * @param int|string $contextId Identifier for the evaluation context (e.g. user ID).
     */
    public function disabledFor(string $name, int|string $contextId): bool
    {
        return !$this->driver->enabledFor($name, $contextId);
    }

    /**
     * Returns all flags and their enabled state.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        return $this->driver->all();
    }
}
