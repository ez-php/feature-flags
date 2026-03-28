<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use EzPhp\FeatureFlags\FlagDriverInterface;

/**
 * Class ArrayDriver
 *
 * In-memory driver backed by a plain PHP array.
 * Intended for testing and for hard-coding flags in application code.
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class ArrayDriver implements FlagDriverInterface
{
    /**
     * @param array<string, bool> $flags
     */
    public function __construct(private readonly array $flags)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    /**
     * {@inheritDoc}
     *
     * ArrayDriver has no per-context storage — delegates to enabled().
     */
    public function enabledFor(string $name, int|string $contextId): bool
    {
        return $this->enabled($name);
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->flags;
    }
}
