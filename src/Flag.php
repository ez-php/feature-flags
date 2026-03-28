<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags;

use RuntimeException;

/**
 * Class Flag
 *
 * Static facade for evaluating feature flags.
 *
 * Usage:
 *
 *   if (Flag::enabled('new-checkout')) { ... }
 *   if (Flag::disabled('dark-mode'))   { ... }
 *   $all = Flag::all();
 *
 * Must be initialised by `FeatureFlagServiceProvider::boot()` before use.
 * Throws `RuntimeException` when called before initialisation (fail-fast).
 *
 * @package EzPhp\FeatureFlags
 */
final class Flag
{
    private static ?FlagManager $manager = null;

    /**
     * Initialise the facade with the resolved manager instance.
     * Called by `FeatureFlagServiceProvider::boot()`.
     */
    public static function setManager(FlagManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Reset the facade — used in test tearDown to prevent state leaking.
     */
    public static function resetManager(): void
    {
        self::$manager = null;
    }

    /**
     * Returns true when the named flag is enabled.
     * Returns false for unknown flags.
     */
    public static function enabled(string $name): bool
    {
        return self::manager()->enabled($name);
    }

    /**
     * Returns true when the named flag is enabled for the given context.
     * Falls back to the global enabled() state when no context-specific override exists.
     *
     * @param int|string $contextId Identifier for the evaluation context (e.g. user ID).
     */
    public static function enabledFor(string $name, int|string $contextId): bool
    {
        return self::manager()->enabledFor($name, $contextId);
    }

    /**
     * Returns true when the named flag is disabled or unknown.
     */
    public static function disabled(string $name): bool
    {
        return self::manager()->disabled($name);
    }

    /**
     * Returns true when the named flag is disabled or unknown for the given context.
     *
     * @param int|string $contextId Identifier for the evaluation context (e.g. user ID).
     */
    public static function disabledFor(string $name, int|string $contextId): bool
    {
        return self::manager()->disabledFor($name, $contextId);
    }

    /**
     * Returns all flags and their enabled state.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return self::manager()->all();
    }

    /**
     * Resolve the manager singleton, throwing when not initialised.
     */
    private static function manager(): FlagManager
    {
        if (self::$manager === null) {
            throw new RuntimeException(
                'Flag facade is not initialised. Add FeatureFlagServiceProvider to your application.'
            );
        }

        return self::$manager;
    }
}
