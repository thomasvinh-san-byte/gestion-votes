<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RedisProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @group redis-boot
 *
 * Tests that RedisProvider::connection() throws RuntimeException when Redis is unavailable.
 * This covers the mandatory health check added to Application::boot() and bootCli().
 * We do NOT call Application::boot() directly because it has global side effects
 * (security headers, error handlers, etc.).
 */
class ApplicationBootTest extends TestCase {
    protected function setUp(): void {
        RedisProvider::reset();
    }

    protected function tearDown(): void {
        RedisProvider::reset();
    }

    public function testRedisProviderThrowsWhenUnavailable(): void {
        RedisProvider::configure([
            'host' => '192.0.2.1', // RFC 5737 TEST-NET — guaranteed unreachable
            'port' => 1,
            'timeout' => 0.1,
        ]);

        $this->expectException(RuntimeException::class);
        RedisProvider::connection();
    }

    public function testRedisProviderThrowsMessageContainsHost(): void {
        RedisProvider::configure([
            'host' => '192.0.2.1', // RFC 5737 TEST-NET — guaranteed unreachable
            'port' => 1,
            'timeout' => 0.1,
        ]);

        try {
            RedisProvider::connection();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // When phpredis extension is available, the error contains the host.
            // When the extension is not installed, the error says so instead.
            // Either way, a clear RuntimeException is thrown — that is the invariant.
            $this->assertNotEmpty($e->getMessage());
            if (extension_loaded('redis')) {
                $this->assertStringContainsString('192.0.2.1', $e->getMessage());
            }
        }
    }
}
