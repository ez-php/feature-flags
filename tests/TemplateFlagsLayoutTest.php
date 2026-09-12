<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\FeatureFlags\Driver\FileDriver;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class TemplateFlagsLayoutTest
 *
 * Pins the two flag files apart.
 *
 * The template shipped `flags.file => 'config/flags.php'` — the driver's own
 * config file. `FileDriver` then `require`d it and cast its two keys to bool, so
 * `Flag::enabled('driver')` and `Flag::enabled('file')` returned `true` and every
 * real flag returned `false`. Silent, because a missing flag is simply `false`.
 *
 * The cause is structural: `ConfigLoader` globs `config/*.php` and keys each file
 * by its basename, which is where `flags.driver` / `flags.file` come from. Any
 * definitions file under `config/` is therefore also a config namespace. These
 * tests fail if the default is ever moved back there.
 *
 * Skipped when the application template is absent (the module is also released
 * standalone, without the monorepo around it).
 *
 * @package Tests
 */
#[CoversClass(FileDriver::class)]
final class TemplateFlagsLayoutTest extends TestCase
{
    /**
     * Absolute path to the application template, or null when not present.
     *
     * @return string|null
     */
    private function templatePath(): ?string
    {
        $path = dirname(__DIR__, 3) . '/ez-php';

        return is_dir($path) ? $path : null;
    }

    /**
     * @return void
     */
    public function test_the_definitions_file_is_not_itself_a_config_file(): void
    {
        $template = $this->templatePath();

        if ($template === null) {
            self::markTestSkipped('Application template not present (standalone module checkout).');
        }

        /** @var mixed $config */
        $config = require $template . '/config/flags.php';
        self::assertIsArray($config);

        $file = $config['file'] ?? null;
        self::assertIsString($file);

        self::assertStringStartsNotWith(
            'config/',
            $file,
            'flags.file must point outside config/ — ConfigLoader loads everything in there, '
            . 'so a definitions file placed there is the driver config itself.',
        );
    }

    /**
     * The path the config names must actually resolve to a shipped file, and that
     * file must contain flag definitions — not driver configuration.
     *
     * @return void
     */
    public function test_the_shipped_definitions_file_holds_only_flags(): void
    {
        $template = $this->templatePath();

        if ($template === null) {
            self::markTestSkipped('Application template not present (standalone module checkout).');
        }

        /** @var mixed $config */
        $config = require $template . '/config/flags.php';
        self::assertIsArray($config);

        $file = $config['file'] ?? null;
        self::assertIsString($file);

        $definitions = $template . '/' . $file;
        self::assertFileExists($definitions, 'The template must ship the file flags.file names.');

        $flags = (new FileDriver($definitions))->all();

        self::assertArrayNotHasKey('driver', $flags, 'The definitions file is being read as driver config.');
        self::assertArrayNotHasKey('file', $flags, 'The definitions file is being read as driver config.');
    }
}
