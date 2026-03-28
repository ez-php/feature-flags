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
     * Return all flags and their enabled state.
     *
     * @return array<string, bool>
     */
    public function all(): array;
}
