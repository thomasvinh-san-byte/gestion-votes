<?php
declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RedisProvider;

/**
 * RateLimiter - Brute force attack protection.
 *
 * Uses Redis INCR+EXPIRE when available, falls back to file-based storage.
 * Sliding window algorithm with atomic counter operations.
 */
final class RateLimiter
{
    private static string $storageDir = '/tmp/ag-vote-ratelimit';

    public static function configure(array $config): void
    {
        if (!empty($config['storage_dir'])) {
            self::$storageDir = $config['storage_dir'];
        }
    }

    /**
     * Checks and increments the rate limit counter.
     */
    public static function check(
        string $context,
        string $identifier,
        int $maxAttempts = 60,
        int $windowSeconds = 60,
        bool $strict = true
    ): bool {
        $key = self::buildKey($context, $identifier);

        if (self::useRedis()) {
            $result = self::checkRedis($key, $maxAttempts, $windowSeconds);
        } else {
            $result = self::checkFile($key, $maxAttempts, $windowSeconds, time());
        }

        if (!$result['allowed']) {
            if ($strict) {
                self::denyWithRetryAfter($result['retry_after'] ?? $windowSeconds, $context);
            }
            return false;
        }

        return true;
    }

    public static function isLimited(string $context, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $key = self::buildKey($context, $identifier);

        if (self::useRedis()) {
            return self::getCountRedis($key) >= $maxAttempts;
        }

        return self::getCountFile($key, $windowSeconds, time()) >= $maxAttempts;
    }

    public static function reset(string $context, string $identifier): void
    {
        $key = self::buildKey($context, $identifier);

        if (self::useRedis()) {
            try {
                $redis = RedisProvider::connection();
                $redis->del($key);
            } catch (\Throwable) {
                // fall through to file cleanup
            }
        }

        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function cleanup(int $maxAge = 3600): int
    {
        // File cleanup (always run — may have legacy files)
        if (!is_dir(self::$storageDir)) {
            return 0;
        }

        $cleaned = 0;
        $now = time();

        foreach (glob(self::$storageDir . '/*') as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    // ── Redis backend ───────────────────────────────────────────────────

    private static function useRedis(): bool
    {
        if (!RedisProvider::isAvailable()) {
            return false;
        }
        try {
            RedisProvider::connection();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function checkRedis(string $key, int $maxAttempts, int $windowSeconds): array
    {
        try {
            $redis = RedisProvider::connection();
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

            // Atomic increment + set TTL if new
            $pipe = $redis->multi(\Redis::PIPELINE);
            $pipe->incr($key);
            $pipe->ttl($key);
            $results = $pipe->exec();

            $count = (int) $results[0];
            $ttl = (int) $results[1];

            // If this is the first hit, set expiry
            if ($count === 1 || $ttl < 0) {
                $redis->expire($key, $windowSeconds);
                $ttl = $windowSeconds;
            }

            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);

            if ($count > $maxAttempts) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => max(1, $ttl),
                ];
            }

            return [
                'allowed' => true,
                'remaining' => $maxAttempts - $count,
            ];
        } catch (\Throwable $e) {
            error_log('RateLimiter Redis error: ' . $e->getMessage());
            // Fallback to file
            return self::checkFile($key, $maxAttempts, $windowSeconds, time());
        }
    }

    private static function getCountRedis(string $key): int
    {
        try {
            $redis = RedisProvider::connection();
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
            $count = (int) $redis->get($key);
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── File backend (fallback) ─────────────────────────────────────────

    private static function buildKey(string $context, string $identifier): string
    {
        return "ratelimit:{$context}:" . hash('sha256', $identifier);
    }

    private static function checkFile(string $key, int $maxAttempts, int $windowSeconds, int $now): array
    {
        self::ensureStorageDir();

        $file = self::getFilePath($key);
        $windowStart = $now - $windowSeconds;

        $lockFile = $file . '.lock';
        $lock = fopen($lockFile, 'c');

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            return ['allowed' => true, 'remaining' => $maxAttempts];
        }

        try {
            $timestamps = [];
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $timestamps = array_filter(
                        array_map('intval', explode("\n", trim($content))),
                        fn($t) => $t > $windowStart
                    );
                }
            }

            $count = count($timestamps);

            if ($count >= $maxAttempts) {
                if (!empty($timestamps)) {
                    sort($timestamps);
                    $retryAfter = ($timestamps[0] + $windowSeconds) - $now;
                } else {
                    $retryAfter = $windowSeconds;
                }
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $retryAfter)];
            }

            $timestamps[] = $now;
            file_put_contents($file, implode("\n", $timestamps));

            return ['allowed' => true, 'remaining' => $maxAttempts - count($timestamps)];

        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function getCountFile(string $key, int $windowSeconds, int $now): int
    {
        $file = self::getFilePath($key);
        $windowStart = $now - $windowSeconds;

        if (!file_exists($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        return count(array_filter(
            array_map('intval', explode("\n", trim($content))),
            fn($t) => $t > $windowStart
        ));
    }

    private static function getFilePath(string $key): string
    {
        return self::$storageDir . '/' . preg_replace('/[^a-z0-9_:-]/i', '_', $key);
    }

    private static function ensureStorageDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }
    }

    private static function denyWithRetryAfter(int $retryAfter, string $context): never
    {
        error_log(sprintf(
            "RATE_LIMIT | context=%s | ip=%s | retry_after=%d",
            $context,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $retryAfter
        ));

        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $retryAfter);

        echo json_encode([
            'ok' => false,
            'error' => 'rate_limit_exceeded',
            'detail' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            'retry_after' => $retryAfter,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
