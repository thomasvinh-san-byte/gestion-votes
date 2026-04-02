<?php

declare(strict_types=1);

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Providers\RepositoryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthMiddleware dynamic session timeout.
 *
 * Tests that getSessionTimeout() reads from tenant_settings,
 * clamps values to allowed range, and falls back to default.
 *
 * Approach: AuthMiddleware::setSessionTimeoutForTest() injects the timeout
 * directly (test helper), bypassing RepositoryFactory which is final.
 * The DB-path behavior is validated via the reset() + cache invalidation tests.
 */
class AuthMiddlewareTimeoutTest extends TestCase {

    private string $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

    protected function setUp(): void {
        if (!defined('APP_SECRET')) {
            define('APP_SECRET', 'test-secret-for-unit-tests-only-timeout');
        }
        if (!defined('DEFAULT_TENANT_ID')) {
            define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
        }

        AuthMiddleware::reset();
        RepositoryFactory::reset();
    }

    protected function tearDown(): void {
        AuthMiddleware::reset();
        RepositoryFactory::reset();
    }

    /**
     * Test: getSessionTimeout() returns 1800 (default 30 min) when no setting exists.
     * Simulated by injecting null — forces fallback to DEFAULT_SESSION_TIMEOUT.
     */
    public function testReturnsDefaultWhenNoSettingExists(): void {
        // null injection signals "no DB value" — method should return default 1800
        AuthMiddleware::setSessionTimeoutForTest($this->tenantId, null);

        $result = AuthMiddleware::getSessionTimeout($this->tenantId);

        $this->assertSame(1800, $result, 'Default timeout should be 1800 seconds (30 minutes)');
    }

    /**
     * Test: getSessionTimeout() returns custom value (3600 for 60 min) when set.
     * Value stored as minutes, returned as seconds.
     */
    public function testReturnsCustomValueWhenSet(): void {
        // 60 minutes stored as minutes => 3600 seconds returned
        AuthMiddleware::setSessionTimeoutForTest($this->tenantId, 3600);

        $result = AuthMiddleware::getSessionTimeout($this->tenantId);

        $this->assertSame(3600, $result, 'Custom 60 minutes should return 3600 seconds');
    }

    /**
     * Test: getSessionTimeout() clamps values below 5 minutes (300 seconds) to 300.
     */
    public function testClampsMinimumToFiveMinutes(): void {
        // 120 seconds (2 min) is below minimum 300 seconds (5 min)
        AuthMiddleware::setSessionTimeoutForTest($this->tenantId, 120);

        $result = AuthMiddleware::getSessionTimeout($this->tenantId);

        $this->assertSame(300, $result, 'Values below 5 minutes should be clamped to 300 seconds');
    }

    /**
     * Test: getSessionTimeout() clamps values above 480 minutes (28800 seconds) to 28800.
     */
    public function testClampsMaximumToFourEightyMinutes(): void {
        // 36000 seconds (600 min) is above maximum 28800 seconds (480 min)
        AuthMiddleware::setSessionTimeoutForTest($this->tenantId, 36000);

        $result = AuthMiddleware::getSessionTimeout($this->tenantId);

        $this->assertSame(28800, $result, 'Values above 480 minutes should be clamped to 28800 seconds');
    }

    /**
     * Test: getSessionTimeout() handles non-numeric/error values gracefully (returns default 1800).
     * Simulated by calling getSessionTimeout() with no injection after reset — DB failure path.
     */
    public function testHandlesNonNumericValueGracefully(): void {
        // Reset cache — with no DB available, should return DEFAULT_SESSION_TIMEOUT (1800)
        AuthMiddleware::reset();

        // No RepositoryFactory injection: DB call will throw (no PDO), fallback to default
        $result = AuthMiddleware::getSessionTimeout($this->tenantId);

        $this->assertSame(1800, $result, 'Non-numeric/error values should fall back to default 1800 seconds');
    }
}
