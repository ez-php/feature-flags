<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use EzPhp\FeatureFlags\FlagDriverInterface;

/**
 * Class FileDriver
 *
 * Reads feature flags from a PHP file that returns an associative array.
 *
 * Example file (config/flags.php):
 *
 *   return [
 *       'new-checkout' => true,
 *       'dark-mode'    => false,
 *   ];
 *
 * The file is re-evaluated on every call — no caching.
 * Returns false / empty array when the file is missing or returns a non-array.
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class FileDriver implements FlagDriverInterface
{
    /**
     * @param string $path Absolute or relative path to the flags PHP file.
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        return $this->load()[$name] ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->load();
    }

    /**
     * Load and normalise the flags file.
     *
     * @return array<string, bool>
     */
    private function load(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        /** @var mixed $data */
        $data = require $this->path;

        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = (bool) $value;
            }
        }

        return $result;
    }
}
