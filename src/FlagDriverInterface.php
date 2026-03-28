<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags;

/**
 * Interface FlagDriverInterface
 *
 * Contract for all feature flag storage backends.
 *
 * @package EzPhp\FeatureFlags
 */
interface FlagDriverInterface
{
    /**
     * Check whether a single named flag is enabled.
     *
     * Returns false for unknown flags — never throws.
     */
    public function enabled(string $name): bool;

    /**
     * Check whether a named flag is enabled for a specific context (e.g. user ID).
     *
     * Drivers that support per-context overrides resolve the context-specific value
     * first and fall back to the global flag state when no override exists.
     * Drivers without per-context storage simply delegate to enabled().
     *
     * Returns false for unknown flags — never throws.
     *
     * @param int|string $contextId Identifier for the evaluation context (e.g. user ID).
     */
    public function enabledFor(string $name, int|string $contextId): bool;

    /**
     * Return all flags and their enabled state.
     *
     * @return array<string, bool>
     */
    public function all(): array;
}
