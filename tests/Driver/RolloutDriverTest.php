<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\FeatureFlags\Driver\ArrayDriver;
use EzPhp\FeatureFlags\Driver\RolloutDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

#[CoversClass(RolloutDriver::class)]
#[UsesClass(ArrayDriver::class)]
final class RolloutDriverTest extends TestCase
{
    public function test_the_same_context_always_gets_the_same_answer(): void
    {
        $driver = new RolloutDriver(new ArrayDriver([]), ['checkout' => 40]);

        $first = $driver->enabledFor('checkout', 'user-42');

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first, $driver->enabledFor('checkout', 'user-42'));
        }
    }

    public function test_zero_percent_enables_nobody_and_hundred_percent_everybody(): void
    {
        $off = new RolloutDriver(new ArrayDriver(['f' => true]), ['f' => 0]);
        $on = new RolloutDriver(new ArrayDriver(['f' => false]), ['f' => 100]);

        foreach (range(1, 200) as $id) {
            $this->assertFalse($off->enabledFor('f', $id));
            $this->assertTrue($on->enabledFor('f', $id));
        }
    }

    public function test_the_enabled_share_is_close_to_the_percentage(): void
    {
        $driver = new RolloutDriver(new ArrayDriver([]), ['f' => 25]);

        $enabled = 0;

        foreach (range(1, 4000) as $id) {
            if ($driver->enabledFor('f', $id)) {
                $enabled++;
            }
        }

        $this->assertGreaterThan(800, $enabled, 'roughly 25% of 4000');
        $this->assertLessThan(1200, $enabled);
    }

    public function test_raising_the_percentage_only_adds_contexts(): void
    {
        $low = new RolloutDriver(new ArrayDriver([]), ['f' => 10]);
        $high = new RolloutDriver(new ArrayDriver([]), ['f' => 30]);

        foreach (range(1, 1000) as $id) {
            if ($low->enabledFor('f', $id)) {
                $this->assertTrue($high->enabledFor('f', $id), "context {$id} was enabled at 10% but not at 30%");
            }
        }
    }

    public function test_boundary_is_exclusive_at_the_bucket(): void
    {
        $bucket = RolloutDriver::bucket('f', 'someone');

        $this->assertTrue((new RolloutDriver(new ArrayDriver([]), ['f' => $bucket + 1]))->enabledFor('f', 'someone'));
        $this->assertFalse((new RolloutDriver(new ArrayDriver([]), ['f' => $bucket]))->enabledFor('f', 'someone'));
    }

    public function test_different_flags_get_different_slices(): void
    {
        $driver = new RolloutDriver(new ArrayDriver([]), ['a' => 50, 'b' => 50]);

        $differs = false;

        foreach (range(1, 200) as $id) {
            if ($driver->enabledFor('a', $id) !== $driver->enabledFor('b', $id)) {
                $differs = true;
                break;
            }
        }

        $this->assertTrue($differs);
    }

    public function test_out_of_range_percentages_are_clamped(): void
    {
        $driver = new RolloutDriver(new ArrayDriver([]), ['neg' => -5, 'big' => 250]);

        $this->assertFalse($driver->enabledFor('neg', 1));
        $this->assertTrue($driver->enabledFor('big', 1));
    }

    public function test_flags_without_a_rollout_are_delegated(): void
    {
        $driver = new RolloutDriver(new ArrayDriver(['plain' => true, 'off' => false]), ['other' => 50]);

        $this->assertTrue($driver->enabled('plain'));
        $this->assertTrue($driver->enabledFor('plain', 'x'));
        $this->assertFalse($driver->enabled('off'));
        $this->assertFalse($driver->enabled('unknown'));
    }

    public function test_a_rollout_replaces_the_inner_answer(): void
    {
        $driver = new RolloutDriver(new ArrayDriver(['f' => true]), ['f' => 0]);

        $this->assertFalse($driver->enabledFor('f', 'x'));
    }

    public function test_global_state_is_only_on_for_a_full_rollout(): void
    {
        $driver = new RolloutDriver(new ArrayDriver([]), ['partial' => 50, 'full' => 100]);

        $this->assertFalse($driver->enabled('partial'));
        $this->assertTrue($driver->enabled('full'));
        $this->assertSame(['partial' => false, 'full' => true], $driver->all());
    }

    public function test_all_merges_inner_flags_with_rollouts(): void
    {
        $driver = new RolloutDriver(new ArrayDriver(['plain' => true]), ['gradual' => 10]);

        $this->assertSame(['plain' => true, 'gradual' => false], $driver->all());
    }
}
