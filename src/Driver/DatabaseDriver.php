<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags\Driver;

use EzPhp\FeatureFlags\FlagDriverInterface;
use PDO;

/**
 * Class DatabaseDriver
 *
 * Reads feature flags from a `feature_flags` database table via PDO.
 *
 * Expected schema:
 *
 *   CREATE TABLE feature_flags (
 *       name    VARCHAR(255) NOT NULL PRIMARY KEY,
 *       enabled TINYINT(1)   NOT NULL DEFAULT 0
 *   );
 *
 * All query failures (missing table, connection error) are caught internally
 * and cause the driver to return false / empty array — never throws.
 *
 * @package EzPhp\FeatureFlags\Driver
 */
final class DatabaseDriver implements FlagDriverInterface
{
    /**
     * @param PDO $pdo Injected PDO connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function enabled(string $name): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT enabled FROM feature_flags WHERE name = ?'
            );
            $stmt->execute([$name]);

            /** @var mixed $row */
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return false;
            }

            return isset($row['enabled']) && (bool) $row['enabled'];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT name, enabled FROM feature_flags'
            );

            if ($stmt === false) {
                return [];
            }

            /** @var mixed $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($rows)) {
                return [];
            }

            $flags = [];

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                    $flags[$row['name']] = isset($row['enabled']) && (bool) $row['enabled'];
                }
            }

            return $flags;
        } catch (\Throwable) {
            return [];
        }
    }
}
