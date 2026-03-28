<?php

declare(strict_types=1);

namespace Tests\FeatureFlags\Driver;

use EzPhp\FeatureFlags\Driver\FileDriver;
use Tests\TestCase;

/**
 * @covers \EzPhp\FeatureFlags\Driver\FileDriver
 */
final class FileDriverTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'ez_flags_') . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testEnabledReturnsTrueForEnabledFlag(): void
    {
        file_put_contents($this->tempFile, "<?php return ['checkout' => true];");

        $driver = new FileDriver($this->tempFile);

        self::assertTrue($driver->enabled('checkout'));
    }

    public function testEnabledReturnsFalseForDisabledFlag(): void
    {
        file_put_contents($this->tempFile, "<?php return ['dark-mode' => false];");

        $driver = new FileDriver($this->tempFile);

        self::assertFalse($driver->enabled('dark-mode'));
    }

    public function testEnabledReturnsFalseForUnknownFlag(): void
    {
        file_put_contents($this->tempFile, '<?php return [];');

        $driver = new FileDriver($this->tempFile);

        self::assertFalse($driver->enabled('unknown'));
    }

    public function testEnabledReturnsFalseWhenFileMissing(): void
    {
        $driver = new FileDriver('/nonexistent/path/flags.php');

        self::assertFalse($driver->enabled('any-flag'));
    }

    public function testAllReturnsAllFlags(): void
    {
        file_put_contents(
            $this->tempFile,
            "<?php return ['feature-a' => true, 'feature-b' => false];"
        );

        $driver = new FileDriver($this->tempFile);

        self::assertSame(['feature-a' => true, 'feature-b' => false], $driver->all());
    }

    public function testAllReturnsEmptyArrayWhenFileMissing(): void
    {
        $driver = new FileDriver('/nonexistent/path/flags.php');

        self::assertSame([], $driver->all());
    }

    public function testAllReturnsEmptyArrayWhenFileReturnsNonArray(): void
    {
        file_put_contents($this->tempFile, '<?php return "not-an-array";');

        $driver = new FileDriver($this->tempFile);

        self::assertSame([], $driver->all());
    }

    public function testAllCastsBooleanValues(): void
    {
        file_put_contents($this->tempFile, "<?php return ['feature' => 1];");

        $driver = new FileDriver($this->tempFile);

        self::assertSame(['feature' => true], $driver->all());
    }

    public function testEnabledForDelegatesToEnabledWhenFlagIsEnabled(): void
    {
        file_put_contents($this->tempFile, "<?php return ['checkout' => true];");

        $driver = new FileDriver($this->tempFile);

        self::assertTrue($driver->enabledFor('checkout', 99));
    }

    public function testEnabledForReturnsFalseForDisabledFlag(): void
    {
        file_put_contents($this->tempFile, "<?php return ['dark-mode' => false];");

        $driver = new FileDriver($this->tempFile);

        self::assertFalse($driver->enabledFor('dark-mode', 'user-abc'));
    }
}
