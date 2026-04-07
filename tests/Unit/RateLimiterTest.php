<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RedisProvider;
use AgVote\Core\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * @group redis
 *
 * Tests for RateLimiter using Redis Lua path.
 * Requires a running Redis instance (available in the Docker test environment).
 */
class RateLimiterTest extends TestCase {
    protected function setUp(): void {
        RedisProvider::configure([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
        // Flush any leftover rate limit keys from previous test runs
        try {
            $redis = RedisProvider::connection();
            $keys = $redis->keys('*ratelimit*');
            if (!empty($keys)) {
                $redis->del(...$keys);
            }
        } catch (\Throwable) {
            // If Redis is unavailable, tests will fail naturally
        }
    }

    protected function tearDown(): void {
        try {
            $redis = RedisProvider::connection();
            $keys = $redis->keys('*ratelimit*');
            if (!empty($keys)) {
                $redis->del(...$keys);
            }
        } catch (\Throwable) {
            // ignore
        }
        RedisProvider::reset();
    }

    public function testCheckAllowsFirstRequest(): void {
        $result = RateLimiter::check('test', '127.0.0.1', 10, 60, false);

        $this->assertTrue($result);
    }

    public function testCheckAllowsRequestsUnderLimit(): void {
        for ($i = 0; $i < 5; $i++) {
            $result = RateLimiter::check('test', '127.0.0.1', 10, 60, false);
            $this->assertTrue($result, "Request {$i} should be allowed");
        }
    }

    public function testCheckBlocksAfterLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('limit-test', '127.0.0.1', 5, 60, false);
        }

        // La 6eme requete devrait etre bloquee
        $result = RateLimiter::check('limit-test', '127.0.0.1', 5, 60, false);

        $this->assertFalse($result);
    }

    public function testIsLimitedReturnsFalseInitially(): void {
        $result = RateLimiter::isLimited('new-context', '192.168.1.1', 10, 60);

        $this->assertFalse($result);
    }

    public function testIsLimitedReturnsTrueAtLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('limited-context', '192.168.1.1', 5, 60, false);
        }

        $result = RateLimiter::isLimited('limited-context', '192.168.1.1', 5, 60);

        $this->assertTrue($result);
    }

    public function testResetClearsLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('reset-context', '10.0.0.1', 5, 60, false);
        }

        // Verifier qu'on est limite
        $this->assertTrue(RateLimiter::isLimited('reset-context', '10.0.0.1', 5, 60));

        // Reset
        RateLimiter::reset('reset-context', '10.0.0.1');

        // Verifier qu'on n'est plus limite
        $this->assertFalse(RateLimiter::isLimited('reset-context', '10.0.0.1', 5, 60));
    }

    public function testDifferentContextsAreSeparate(): void {
        // Remplir context1
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('context1', '127.0.0.1', 5, 60, false);
        }

        // context1 devrait etre limite
        $this->assertTrue(RateLimiter::isLimited('context1', '127.0.0.1', 5, 60));

        // context2 devrait etre libre
        $this->assertFalse(RateLimiter::isLimited('context2', '127.0.0.1', 5, 60));
    }

    public function testDifferentIdentifiersAreSeparate(): void {
        // Remplir pour IP1
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('shared-context', 'ip1', 5, 60, false);
        }

        // IP1 devrait etre limitee
        $this->assertTrue(RateLimiter::isLimited('shared-context', 'ip1', 5, 60));

        // IP2 devrait etre libre
        $this->assertFalse(RateLimiter::isLimited('shared-context', 'ip2', 5, 60));
    }

    public function testConcurrentAccessHandled(): void {
        // Simuler des acces concurrents (simplifie)
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = RateLimiter::check('concurrent', 'user1', 10, 60, false);
        }

        // Toutes les requetes devraient avoir ete traitees
        $this->assertCount(10, $results);
        $this->assertContainsOnly('bool', $results);
    }

    // =========================================================================
    // STRICT MODE (throws ApiResponseException on limit)
    // =========================================================================

    public function testStrictModeThrowsWhenLimited(): void {
        // Atteindre la limite
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::check('strict-ctx', '10.0.0.1', 3, 60, false);
        }

        // En mode strict, la requete suivante doit lever une exception
        $this->expectException(\AgVote\Core\Http\ApiResponseException::class);
        RateLimiter::check('strict-ctx', '10.0.0.1', 3, 60, true);
    }

    public function testNonStrictModeReturnsFalseWhenLimited(): void {
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::check('nonstrict-ctx', '10.0.0.1', 3, 60, false);
        }

        // En mode non-strict, renvoie simplement false
        $result = RateLimiter::check('nonstrict-ctx', '10.0.0.1', 3, 60, false);
        $this->assertFalse($result);
    }

    // =========================================================================
    // SLIDING WINDOW BEHAVIOR
    // =========================================================================

    public function testWindowSizeOneSecond(): void {
        // Fenetre tres courte (1 seconde), limite de 2
        for ($i = 0; $i < 2; $i++) {
            RateLimiter::check('tiny-window', '127.0.0.1', 2, 1, false);
        }

        // Devrait etre limite immediatement
        $result = RateLimiter::check('tiny-window', '127.0.0.1', 2, 1, false);
        $this->assertFalse($result);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testEmptyIdentifier(): void {
        $result = RateLimiter::check('edge', '', 10, 60, false);
        $this->assertTrue($result, 'Empty identifier should still work');
    }

    public function testSpecialCharactersInIdentifier(): void {
        $result = RateLimiter::check('edge', 'user@domain.com/path?q=1&x=2', 10, 60, false);
        $this->assertTrue($result, 'Special characters in identifier should be hashed safely');
    }

    public function testResetNonExistentKey(): void {
        // Reset sur une cle qui n'existe pas ne devrait pas planter
        RateLimiter::reset('nonexistent-ctx', 'nonexistent-ip');
        $this->assertTrue(true, 'Resetting non-existent key should not throw');
    }

    public function testLimitOfOne(): void {
        $result1 = RateLimiter::check('one-limit', '127.0.0.1', 1, 60, false);
        $this->assertTrue($result1);

        $result2 = RateLimiter::check('one-limit', '127.0.0.1', 1, 60, false);
        $this->assertFalse($result2);
    }

    public function testHighConcurrencySimulation(): void {
        $limit = 100;
        $allowed = 0;
        $blocked = 0;

        for ($i = 0; $i < 150; $i++) {
            if (RateLimiter::check('highload', '10.0.0.1', $limit, 60, false)) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        $this->assertEquals($limit, $allowed, "Exactly {$limit} requests should be allowed");
        $this->assertEquals(50, $blocked, '50 requests should be blocked');
    }

    public function testCleanupReturnsZero(): void {
        // cleanup() is a no-op after Redis migration — Redis TTL handles expiry
        $result = RateLimiter::cleanup(3600);
        $this->assertEquals(0, $result, 'cleanup() should return 0 (no-op, Redis TTL handles expiry)');
    }
}
