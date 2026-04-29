<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Security\AccountLockout;
use PHPUnit\Framework\TestCase;

/**
 * Pure (non-Redis) tests for AccountLockout — F13 progressive backoff.
 *
 * Validates the public, side-effect-free helper that computes lock duration
 * from a failure count. The Redis-backed entry points (status, recordFailure,
 * reset) are exercised by the integration suite (`@group redis`).
 */
final class AccountLockoutPureTest extends TestCase {
    public function testNoLockBeforeThreshold(): void {
        for ($count = 0; $count < 5; $count++) {
            $this->assertSame(
                0,
                AccountLockout::lockSecondsForCount($count),
                "Count {$count} should NOT trigger a lock.",
            );
        }
    }

    public function testFirstLockIsOneMinute(): void {
        // 5 failures → 2^0 = 1 minute = 60 s
        $this->assertSame(60, AccountLockout::lockSecondsForCount(5));
    }

    public function testLockDoublesEachAdditionalFailure(): void {
        $this->assertSame(60,    AccountLockout::lockSecondsForCount(5));   // 1 min
        $this->assertSame(120,   AccountLockout::lockSecondsForCount(6));   // 2 min
        $this->assertSame(240,   AccountLockout::lockSecondsForCount(7));   // 4 min
        $this->assertSame(480,   AccountLockout::lockSecondsForCount(8));   // 8 min
        $this->assertSame(960,   AccountLockout::lockSecondsForCount(9));   // 16 min
        $this->assertSame(1920,  AccountLockout::lockSecondsForCount(10));  // 32 min
        $this->assertSame(3840,  AccountLockout::lockSecondsForCount(11));  // 64 min
    }

    public function testLockCapsAt24Hours(): void {
        // 24 h cap = 86_400 s = 1440 min
        // 2^11 = 2048 > 1440 → capped from count 16 onward
        $cap = 86_400;
        $this->assertLessThanOrEqual($cap, AccountLockout::lockSecondsForCount(15));
        $this->assertSame($cap, AccountLockout::lockSecondsForCount(16));
        $this->assertSame($cap, AccountLockout::lockSecondsForCount(50));
        $this->assertSame($cap, AccountLockout::lockSecondsForCount(1_000));
    }

    public function testLockSecondsAreAlwaysAlignedToOneMinute(): void {
        for ($count = 5; $count <= 30; $count++) {
            $seconds = AccountLockout::lockSecondsForCount($count);
            $this->assertSame(
                0,
                $seconds % 60,
                "Lock duration for count {$count} should be a whole number of minutes.",
            );
        }
    }
}
