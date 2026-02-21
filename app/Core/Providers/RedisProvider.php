<?php
declare(strict_types=1);

namespace AgVote\Core\Providers;

/**
 * RedisProvider - Redis connection management.
 *
 * Provides a singleton Redis connection with graceful fallback:
 * if phpredis extension is not installed, isAvailable() returns false
 * and callers should fall back to file-based implementations.
 */
final class RedisProvider
{
    private static ?\Redis $redis = null;
    private static array $config = [];

    /**
     * Configure Redis connection parameters.
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Check if the phpredis extension is loaded.
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('redis');
    }

    /**
     * Get or create a Redis connection.
     *
     * @throws \RuntimeException if extension is not available or connection fails
     */
    public static function connection(): \Redis
    {
        if (self::$redis !== null) {
            try {
                self::$redis->ping();
                return self::$redis;
            } catch (\Throwable) {
                self::$redis = null;
            }
        }

        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'Redis extension (phpredis) is not installed. '
                . 'Install with: pecl install redis && docker-php-ext-enable redis'
            );
        }

        $host = (string)(self::$config['host'] ?? (getenv('REDIS_HOST') ?: '127.0.0.1'));
        $port = (int)(self::$config['port'] ?? (getenv('REDIS_PORT') ?: 6379));
        $password = (string)(self::$config['password'] ?? (getenv('REDIS_PASSWORD') ?: ''));
        $database = (int)(self::$config['database'] ?? (getenv('REDIS_DATABASE') ?: 0));
        $timeout = (float)(self::$config['timeout'] ?? 2.0);
        $prefix = (string)(self::$config['prefix'] ?? (getenv('REDIS_PREFIX') ?: 'agvote:'));

        $redis = new \Redis();

        $connected = $redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new \RuntimeException("Redis connection failed: {$host}:{$port}");
        }

        if ($password !== '') {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        $redis->setOption(\Redis::OPT_PREFIX, $prefix);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);

        self::$redis = $redis;

        return self::$redis;
    }

    /**
     * Close the connection and reset state.
     */
    public static function disconnect(): void
    {
        if (self::$redis !== null) {
            try {
                self::$redis->close();
            } catch (\Throwable) {
                // ignore
            }
            self::$redis = null;
        }
    }

    /**
     * Reset for testing.
     */
    public static function reset(): void
    {
        self::disconnect();
        self::$config = [];
    }
}
