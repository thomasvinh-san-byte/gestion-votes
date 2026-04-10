<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Security\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SessionManager extracted class.
 *
 * Proves SessionManager is independently testable without AuthMiddleware.
 * Uses setSessionTimeoutForTest() to inject values, no DB required.
 */
final class SessionManagerTest extends TestCase {

    private string $tenantId = 'aaaaaaaa-1111-2222-3333-444444444444';

    protected function setUp(): void {
        if (!defined('APP_SECRET')) {
            define('APP_SECRET', 'test-secret-session-manager');
        }
        if (!defined('DEFAULT_TENANT_ID')) {
            define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
        }

        SessionManager::reset();
    }

    protected function tearDown(): void {
        SessionManager::reset();
    }

    // =========================================================================
    // getSessionTimeout — default behavior
    // =========================================================================

    /**
     * Test: getSessionTimeout returns DEFAULT_SESSION_TIMEOUT (1800) when no DB config.
     */
    public function testGetSessionTimeoutReturnsDefaultWhenNoConfig(): void {
        // No test override, no DB => fallback to 1800
        $result = SessionManager::getSessionTimeout($this->tenantId);

        $this->assertSame(1800, $result, 'Default timeout should be 1800 seconds (30 minutes)');
    }

    // =========================================================================
    // setSessionTimeoutForTest — override and clear
    // =========================================================================

    /**
     * Test: setSessionTimeoutForTest overrides getSessionTimeout for given tenant.
     */
    public function testSetSessionTimeoutForTestOverridesValue(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 3600);

        $result = SessionManager::getSessionTimeout($this->tenantId);

        $this->assertSame(3600, $result, 'Injected 3600 should be returned');
    }

    /**
     * Test: setSessionTimeoutForTest(tenantId, null) clears override, reverts to default.
     */
    public function testSetSessionTimeoutForTestNullClearsOverride(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 7200);
        $this->assertSame(7200, SessionManager::getSessionTimeout($this->tenantId));

        // Clear override
        SessionManager::setSessionTimeoutForTest($this->tenantId, null);

        // Should revert to default (no DB available => 1800)
        $result = SessionManager::getSessionTimeout($this->tenantId);
        $this->assertSame(1800, $result, 'Clearing override should revert to default 1800');
    }

    // =========================================================================
    // getSessionTimeout — clamping
    // =========================================================================

    /**
     * Test: getSessionTimeout clamps values below 300 to 300.
     */
    public function testGetSessionTimeoutClampsMinimumTo300(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 60);

        $result = SessionManager::getSessionTimeout($this->tenantId);

        $this->assertSame(300, $result, 'Values below 300 should be clamped to 300');
    }

    /**
     * Test: getSessionTimeout clamps values above 28800 to 28800.
     */
    public function testGetSessionTimeoutClampsMaximumTo28800(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 99999);

        $result = SessionManager::getSessionTimeout($this->tenantId);

        $this->assertSame(28800, $result, 'Values above 28800 should be clamped to 28800');
    }

    // =========================================================================
    // checkExpiry
    // =========================================================================

    /**
     * Test: checkExpiry returns true when lastActivity exceeds timeout.
     */
    public function testCheckExpiryReturnsTrueWhenExpired(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 300);

        // Last activity was 999 seconds ago, timeout is 300 => expired
        $lastActivity = time() - 999;
        $result = SessionManager::checkExpiry($lastActivity, $this->tenantId);

        $this->assertTrue($result, 'Session should be expired when lastActivity exceeds timeout');
    }

    /**
     * Test: checkExpiry returns false when lastActivity is within timeout.
     */
    public function testCheckExpiryReturnsFalseWhenNotExpired(): void {
        SessionManager::setSessionTimeoutForTest($this->tenantId, 3600);

        // Last activity was 10 seconds ago, timeout is 3600 => not expired
        $lastActivity = time() - 10;
        $result = SessionManager::checkExpiry($lastActivity, $this->tenantId);

        $this->assertFalse($result, 'Session should not be expired when lastActivity is within timeout');
    }

    // =========================================================================
    // isSessionExpired / setSessionExpired / consumeSessionExpired
    // =========================================================================

    /**
     * Test: isSessionExpired returns false initially, true after setSessionExpired(true).
     */
    public function testIsSessionExpiredInitiallyFalseThenTrue(): void {
        $this->assertFalse(SessionManager::isSessionExpired(), 'Should be false initially');

        SessionManager::setSessionExpired(true);

        $this->assertTrue(SessionManager::isSessionExpired(), 'Should be true after setSessionExpired(true)');
    }

    /**
     * Test: consumeSessionExpired returns true once then false on second call.
     */
    public function testConsumeSessionExpiredReturnsTrueThenFalse(): void {
        SessionManager::setSessionExpired(true);

        $first = SessionManager::consumeSessionExpired();
        $second = SessionManager::consumeSessionExpired();

        $this->assertTrue($first, 'First consumeSessionExpired() should return true');
        $this->assertFalse($second, 'Second consumeSessionExpired() should return false');
    }

    // =========================================================================
    // reset
    // =========================================================================

    /**
     * Test: reset clears all cached state (timeout cache, test overrides, expiry flag).
     */
    public function testResetClearsAllState(): void {
        // Set various state
        SessionManager::setSessionTimeoutForTest($this->tenantId, 7200);
        SessionManager::setSessionExpired(true);
        // Prime the cache
        SessionManager::getSessionTimeout($this->tenantId);

        // Reset
        SessionManager::reset();

        // Verify all cleared
        $this->assertFalse(SessionManager::isSessionExpired(), 'Expiry flag should be cleared');
        // After reset, no test override => should fall back to default
        $this->assertSame(1800, SessionManager::getSessionTimeout($this->tenantId), 'Timeout should revert to default');
    }

    // =========================================================================
    // getRevalidateInterval
    // =========================================================================

    /**
     * Test: getRevalidateInterval returns 60 (the constant).
     */
    public function testGetRevalidateIntervalReturns60(): void {
        $result = SessionManager::getRevalidateInterval();

        $this->assertSame(60, $result, 'Revalidate interval should be 60 seconds');
    }
}
