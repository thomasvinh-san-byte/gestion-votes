<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RedisProvider;
use Redis;

/**
 * RateLimiter - Brute force attack protection.
 *
 * Uses Redis Lua EVAL for atomic INCR+EXPIRE. Redis is mandatory — no file fallback.
 * The Lua script guarantees atomicity: INCR and EXPIRE happen in a single Redis command slot,
 * eliminating the race condition present in non-atomic multi-command approaches.
 */
final class RateLimiter {
    /**
     * Lua script for atomic rate-limit increment with sliding TTL.
     *
     * KEYS[1] = rate limit key
     * ARGV[1] = window seconds (string)
     *
     * Returns {current_count, ttl}
     */
    private const RATE_LIMIT_LUA = <<<'LUA'
        local current = redis.call('INCR', KEYS[1])
        if current == 1 then
            redis.call('EXPIRE', KEYS[1], tonumber(ARGV[1]))
        end
        local ttl = redis.call('TTL', KEYS[1])
        return {current, ttl}
    LUA;

    /**
     * Checks and increments the rate limit counter.
     *
     * @throws \AgVote\Core\Http\ApiResponseException in strict mode when limit is exceeded
     */
    public static function check(
        string $context,
        string $identifier,
        int $maxAttempts = 60,
        int $windowSeconds = 60,
        bool $strict = true,
    ): bool {
        $key = self::buildKey($context, $identifier);
        $result = self::checkRedis($key, $maxAttempts, $windowSeconds);

        if (!$result['allowed']) {
            if ($strict) {
                self::denyWithRetryAfter($result['retry_after'] ?? $windowSeconds, $context);
            }
            return false;
        }
        return true;
    }

    /**
     * Returns true if the identifier has exceeded the rate limit (without incrementing).
     */
    public static function isLimited(string $context, string $identifier, int $maxAttempts, int $windowSeconds): bool {
        $key = self::buildKey($context, $identifier);
        return self::getCountRedis($key) >= $maxAttempts;
    }

    /**
     * Resets the rate limit counter for the given context + identifier.
     */
    public static function reset(string $context, string $identifier): void {
        $key = self::buildKey($context, $identifier);
        $redis = RedisProvider::connection();
        $redis->del($key);
    }

    /**
     * No-op. Redis TTL handles counter expiry automatically.
     * Kept for API compatibility with callers that may invoke cleanup() on a schedule.
     */
    public static function cleanup(int $maxAge = 3600): int {
        return 0;
    }

    // ── Redis backend ───────────────────────────────────────────────────

    /**
     * @return array{allowed: bool, remaining: int, retry_after?: int}
     */
    private static function checkRedis(string $key, int $maxAttempts, int $windowSeconds): array {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        try {
            /** @var array{0: int, 1: int} $result */
            $result = $redis->eval(self::RATE_LIMIT_LUA, [$key, (string) $windowSeconds], 1);

            $count = (int) $result[0];
            $ttl = (int) $result[1];

            if ($count > $maxAttempts) {
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $ttl)];
            }
            return ['allowed' => true, 'remaining' => $maxAttempts - $count];
        } finally {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
    }

    private static function getCountRedis(string $key): int {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        try {
            return (int) $redis->get($key);
        } finally {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
    }

    private static function buildKey(string $context, string $identifier): string {
        return "ratelimit:{$context}:" . hash('sha256', $identifier);
    }

    private static function denyWithRetryAfter(int $retryAfter, string $context): never {
        error_log(sprintf(
            'RATE_LIMIT | context=%s | ip=%s | retry_after=%d',
            $context,
            \AgVote\Core\Http\ClientIp::get(),
            $retryAfter,
        ));

        throw new \AgVote\Core\Http\ApiResponseException(
            new \AgVote\Core\Http\JsonResponse(429, [
                'ok' => false,
                'error' => 'rate_limit_exceeded',
                'detail' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                'retry_after' => $retryAfter,
            ], [
                'Retry-After' => (string) $retryAfter,
            ]),
        );
    }
}
