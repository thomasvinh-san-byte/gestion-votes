<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RedisProvider;
use Throwable;

/**
 * AccountLockout — F13 progressive per-account lockout.
 *
 * The pre-existing RateLimiter is keyed by IP, which protects against
 * single-source brute force but does nothing against a slow distributed
 * attack rotating through proxies. This adds a complementary, per-account
 * counter:
 *
 *   - Each failed login increments the failure count for the account.
 *   - At thresholds (5, 10, 15, 20+ failures) the account is locked for
 *     2^n minutes, capped at 24 h.
 *   - The counter (and lock) reset on a successful login.
 *
 * The account identifier is never stored in clear — only its SHA-256 hash
 * is used for the Redis key, so an attacker with Redis read access cannot
 * enumerate which emails are under attack.
 *
 * Failure modes are silent — Redis unavailable returns "no lock", on the
 * principle that the per-IP RateLimiter remains the primary gate.
 */
final class AccountLockout {
    private const COUNTER_PREFIX = 'lockout:auth:counter:';
    private const LOCK_PREFIX    = 'lockout:auth:locked:';
    private const COUNTER_TTL    = 86_400;      // 24 h sliding window
    private const FIRST_LOCK_THRESHOLD = 5;     // failures before any lock
    private const LOCK_CAP_SECONDS = 86_400;    // 24 h max

    /**
     * @return array{locked: bool, retry_after_seconds: int}
     */
    public static function status(string $accountIdentifier): array {
        $key = self::lockKey($accountIdentifier);
        try {
            $redis = RedisProvider::connection();
            $ttl = (int) $redis->ttl($key);
        } catch (Throwable) {
            return ['locked' => false, 'retry_after_seconds' => 0];
        }
        if ($ttl > 0) {
            return ['locked' => true, 'retry_after_seconds' => $ttl];
        }
        return ['locked' => false, 'retry_after_seconds' => 0];
    }

    /**
     * Increment failure count and, past the threshold, set a lock with
     * exponential backoff. Returns the new (post-increment) state.
     *
     * @return array{count: int, locked: bool, retry_after_seconds: int}
     */
    public static function recordFailure(string $accountIdentifier): array {
        $counterKey = self::counterKey($accountIdentifier);
        $lockKey = self::lockKey($accountIdentifier);

        try {
            $redis = RedisProvider::connection();
            $count = (int) $redis->incr($counterKey);
            // Reset counter sliding window on each new failure.
            $redis->expire($counterKey, self::COUNTER_TTL);
        } catch (Throwable) {
            return ['count' => 0, 'locked' => false, 'retry_after_seconds' => 0];
        }

        if ($count < self::FIRST_LOCK_THRESHOLD) {
            return ['count' => $count, 'locked' => false, 'retry_after_seconds' => 0];
        }

        // Backoff in minutes: 1, 2, 4, 8, 16, 32, 64, ... cap at 24 h.
        // n = excess failures past the threshold (count=5 → n=0 → 1 min)
        $n = $count - self::FIRST_LOCK_THRESHOLD;
        $minutes = min(2 ** $n, (int) (self::LOCK_CAP_SECONDS / 60));
        $seconds = $minutes * 60;

        try {
            $redis = RedisProvider::connection();
            $redis->set($lockKey, '1', ['EX' => $seconds]);
        } catch (Throwable) {
            return ['count' => $count, 'locked' => false, 'retry_after_seconds' => 0];
        }

        return ['count' => $count, 'locked' => true, 'retry_after_seconds' => $seconds];
    }

    /**
     * Reset the lockout state for an account — call on successful login.
     */
    public static function reset(string $accountIdentifier): void {
        try {
            $redis = RedisProvider::connection();
            $redis->del(self::counterKey($accountIdentifier));
            $redis->del(self::lockKey($accountIdentifier));
        } catch (Throwable) {
            // ignore — Redis unavailable is non-blocking for login success.
        }
    }

    /**
     * Compute the lock duration that recordFailure would set for a given
     * raw failure count. Pure function — useful for tests and audit logs.
     */
    public static function lockSecondsForCount(int $count): int {
        if ($count < self::FIRST_LOCK_THRESHOLD) {
            return 0;
        }
        $n = $count - self::FIRST_LOCK_THRESHOLD;
        $minutes = min(2 ** $n, (int) (self::LOCK_CAP_SECONDS / 60));
        return $minutes * 60;
    }

    private static function counterKey(string $accountIdentifier): string {
        return self::COUNTER_PREFIX . hash('sha256', strtolower(trim($accountIdentifier)));
    }

    private static function lockKey(string $accountIdentifier): string {
        return self::LOCK_PREFIX . hash('sha256', strtolower(trim($accountIdentifier)));
    }
}
