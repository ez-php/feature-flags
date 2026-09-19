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
use EzPhp\FeatureFlags\Driver\RedisDriver;
use EzPhp\FeatureFlags\Driver\RolloutDriver;
use Redis;

/**
 * Class FeatureFlagServiceProvider
 *
 * Registers the FlagManager and initialises the Flag static facade.
 *
 * Driver selection is controlled by `flags.driver` (default: `file`):
 *
 *   - `file`     — reads `flags.file` config key (default: `flags.php`)
 *   - `database` — reads from `feature_flags` table via DatabaseInterface
 *   - `redis`    — reads from Redis hashes; connects using `flags.redis.host`/`flags.redis.port`/`flags.redis.database`
 *   - `array`    — empty in-memory driver (useful for tests / CI environments)
 *
 * `flags.rollouts` (flag name → percentage 0–100) wraps whichever driver is selected in a
 * `RolloutDriver`, so `Flag::enabledFor()` enables a stable slice of contexts (e.g. users).
 *
 * @package EzPhp\FeatureFlags
 */
final class FeatureFlagServiceProvider extends ServiceProvider
{
    /**
     * Default location of the flag definitions file.
     *
     * Note the absence of a `config/` prefix. The framework's ConfigLoader globs
     * `config/*.php` and keys each file by its basename, which is where the
     * `flags.driver` / `flags.file` keys come from. A definitions file at
     * `config/flags.php` would therefore be the driver's own config file: the
     * driver would report `driver` and `file` as enabled flags and find none of
     * the real ones.
     */
    private const string DEFAULT_FILE = 'flags.php';

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

            return new FlagManager($this->withRollouts($this->makeDriver($app, $config, $driverName), $config));
        });
    }

    /**
     * Build the storage driver selected by `flags.driver`.
     *
     * @param ContainerInterface   $app
     * @param ConfigInterface|null $config
     * @param string               $driverName
     *
     * @return FlagDriverInterface
     */
    private function makeDriver(ContainerInterface $app, ?ConfigInterface $config, string $driverName): FlagDriverInterface
    {
        if ($driverName === 'database') {
            $pdo = $app->make(DatabaseInterface::class)->getPdo();

            return new DatabaseDriver($pdo);
        }

        if ($driverName === 'redis') {
            $hostValue = $config?->get('flags.redis.host', '127.0.0.1');
            $portValue = $config?->get('flags.redis.port', 6379);
            $databaseValue = $config?->get('flags.redis.database', 0);
            $host = is_string($hostValue) ? $hostValue : '127.0.0.1';
            $port = is_int($portValue) ? $portValue : 6379;
            $database = is_int($databaseValue) ? $databaseValue : 0;

            $redis = new Redis();
            $redis->connect($host, $port);
            $redis->select($database);

            return new RedisDriver($redis);
        }

        if ($driverName === 'array') {
            return new ArrayDriver([]);
        }

        $rawPath = $config?->get('flags.file', self::DEFAULT_FILE);
        $path = is_string($rawPath) ? $rawPath : self::DEFAULT_FILE;

        return new FileDriver($path);
    }

    /**
     * Wrap the driver in a RolloutDriver when `flags.rollouts` defines percentages.
     *
     * @param FlagDriverInterface  $driver
     * @param ConfigInterface|null $config
     *
     * @return FlagDriverInterface
     */
    private function withRollouts(FlagDriverInterface $driver, ?ConfigInterface $config): FlagDriverInterface
    {
        $raw = $config?->get('flags.rollouts', []);

        if (!is_array($raw) || $raw === []) {
            return $driver;
        }

        $rollouts = [];

        foreach ($raw as $name => $percent) {
            if (is_string($name) && is_int($percent)) {
                $rollouts[$name] = $percent;
            }
        }

        return $rollouts === [] ? $driver : new RolloutDriver($driver, $rollouts);
    }

    /**
     * Initialise the Flag static facade.
     */
    public function boot(): void
    {
        Flag::setManager($this->app->make(FlagManager::class));
    }
}
