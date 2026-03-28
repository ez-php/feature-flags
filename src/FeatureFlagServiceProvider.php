<?php

declare(strict_types=1);

namespace EzPhp\FeatureFlags;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\Driver\DatabaseDriver;
use EzPhp\FeatureFlags\Driver\FileDriver;

/**
 * Class FeatureFlagServiceProvider
 *
 * Registers the FlagManager and initialises the Flag static facade.
 *
 * Driver selection is controlled by `flags.driver` (default: `file`):
 *
 *   - `file`     — reads `flags.file` config key (default: `config/flags.php`)
 *   - `database` — reads from `feature_flags` table via DatabaseInterface
 *   - `array`    — empty in-memory driver (useful for tests / CI environments)
 *
 * @package EzPhp\FeatureFlags
 */
final class FeatureFlagServiceProvider extends ServiceProvider
{
    /**
     * Bind FlagManager to the container.
     */
    public function register(): void
    {
        $this->app->bind(FlagManager::class, function (ContainerInterface $app): FlagManager {
            $config = null;

            try {
                $config = $app->make(ConfigInterface::class);
            } catch (\Throwable) {
                // Config not available — fall back to defaults
            }

            $rawDriver = $config?->get('flags.driver', 'file');
            $driverName = is_string($rawDriver) ? $rawDriver : 'file';

            if ($driverName === 'database') {
                $pdo = $app->make(DatabaseInterface::class)->getPdo();

                return new FlagManager(new DatabaseDriver($pdo));
            }

            if ($driverName === 'array') {
                return new FlagManager(new ArrayDriver([]));
            }

            $rawPath = $config?->get('flags.file', 'config/flags.php');
            $path = is_string($rawPath) ? $rawPath : 'config/flags.php';

            return new FlagManager(new FileDriver($path));
        });
    }

    /**
     * Initialise the Flag static facade.
     */
    public function boot(): void
    {
        Flag::setManager($this->app->make(FlagManager::class));
    }
}
