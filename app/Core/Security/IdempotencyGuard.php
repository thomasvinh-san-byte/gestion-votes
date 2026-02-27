<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RedisProvider;
use Throwable;

/**
 * Idempotency guard for POST endpoints.
 *
 * Reads the X-Idempotency-Key header and checks Redis for a cached response.
 * If found, returns the cached response immediately. If not, returns null
 * so the caller can proceed, then call store() with the result.
 *
 * Falls back to no-op when Redis is unavailable (graceful degradation).
 */
final class IdempotencyGuard
{
    private const TTL = 3600; // 1 hour
    private const PREFIX = 'idempotency:';

    /**
     * Check if a response is already cached for this idempotency key.
     *
     * @return array|null Cached response data, or null if not found / no key
     */
    public static function check(): ?array
    {
        $key = self::getKey();
        if ($key === null) {
            return null;
        }

        try {
            if (!RedisProvider::isAvailable()) {
                return null;
            }
            $redis = RedisProvider::connection();
            $cached = $redis->get(self::PREFIX . $key);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable) {
            // Redis unavailable — proceed without idempotency
        }

        return null;
    }

    /**
     * Store the response for this idempotency key.
     *
     * @param array $responseData Data to cache
     */
    public static function store(array $responseData): void
    {
        $key = self::getKey();
        if ($key === null) {
            return;
        }

        try {
            if (!RedisProvider::isAvailable()) {
                return;
            }
            $redis = RedisProvider::connection();
            $redis->setex(self::PREFIX . $key, self::TTL, $responseData);
        } catch (Throwable) {
            // Redis unavailable — skip caching
        }
    }

    /**
     * Get the idempotency key from the request header.
     */
    public static function getKey(): ?string
    {
        $key = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
        if ($key === null || trim($key) === '') {
            return null;
        }
        return trim($key);
    }
}
