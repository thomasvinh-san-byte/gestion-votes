<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RedisProvider;
use AgVote\Core\Security\IdempotencyGuard;
use PHPUnit\Framework\TestCase;

/**
 * @group redis
 *
 * Tests for IdempotencyGuard check/store/reject cycle.
 * Requires a running Redis instance (available in the Docker test environment).
 */
class IdempotencyGuardTest extends TestCase
{
    private bool $redisAvailable = false;

    protected function setUp(): void
    {
        RedisProvider::configure([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
        // Check if Redis is actually available
        try {
            if (RedisProvider::isAvailable()) {
                $redis = RedisProvider::connection();
                $redis->ping();
                $this->redisAvailable = true;
                // Flush any leftover idempotency keys from previous test runs
                $keys = $redis->keys('idempotency:*');
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            }
        } catch (\Throwable) {
            $this->redisAvailable = false;
        }
    }

    private function requireRedis(): void
    {
        if (!$this->redisAvailable) {
            $this->markTestSkipped('Redis extension (phpredis) not available');
        }
    }

    protected function tearDown(): void
    {
        try {
            $redis = RedisProvider::connection();
            $keys = $redis->keys('idempotency:*');
            if (!empty($keys)) {
                $redis->del(...$keys);
            }
        } catch (\Throwable) {
            // ignore
        }
        RedisProvider::reset();
        unset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
    }

    private function setIdempotencyKey(string $key): void
    {
        $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] = $key;
    }

    public function testCheckReturnsNullWithoutKey(): void
    {
        // No header set at all
        unset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);

        $result = IdempotencyGuard::check();

        $this->assertNull($result);
    }

    public function testCheckReturnsNullOnFirstCall(): void
    {
        $this->requireRedis();
        $this->setIdempotencyKey('test-uuid-first-call');

        $result = IdempotencyGuard::check();

        $this->assertNull($result, 'First call with a new key should return null (nothing cached)');
    }

    public function testStoreAndCheckReturnsCachedResponse(): void
    {
        $this->requireRedis();
        $this->setIdempotencyKey('test-uuid-store-check');
        $responseData = ['ok' => true, 'id' => 'abc'];

        IdempotencyGuard::store($responseData);
        $result = IdempotencyGuard::check();

        $this->assertEquals($responseData, $result, 'Second call with same key should return cached response');
    }

    public function testDifferentKeyReturnsNull(): void
    {
        $this->requireRedis();
        $this->setIdempotencyKey('key-alpha');
        IdempotencyGuard::store(['ok' => true, 'id' => 'stored-for-alpha']);

        // Switch to a different key
        $this->setIdempotencyKey('key-beta');
        $result = IdempotencyGuard::check();

        $this->assertNull($result, 'Different key should not return data cached under another key');
    }

    public function testGetKeyTrimsWhitespace(): void
    {
        $this->setIdempotencyKey(' uuid-with-spaces ');

        $result = IdempotencyGuard::getKey();

        $this->assertSame('uuid-with-spaces', $result);
    }

    public function testGetKeyReturnsNullForEmptyString(): void
    {
        $this->setIdempotencyKey('');

        $result = IdempotencyGuard::getKey();

        $this->assertNull($result);
    }
}
