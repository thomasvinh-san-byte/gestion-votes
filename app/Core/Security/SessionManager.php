<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RepositoryFactory;
use Throwable;

/**
 * SessionManager - Session timeout, expiry checking, and DB revalidation.
 *
 * Extracted from AuthMiddleware to isolate session lifecycle concerns.
 * All methods are static to match the existing AuthMiddleware pattern.
 */
final class SessionManager {
    /** Default session timeout in seconds (30 minutes) */
    private const DEFAULT_SESSION_TIMEOUT = 1800;

    /** Interval (seconds) between DB re-validation of session user */
    private const SESSION_REVALIDATE_INTERVAL = 60;

    // =========================================================================
    // STATE
    // =========================================================================

    /** True when the last authenticate() call detected an expired session. */
    private static bool $sessionExpired = false;

    /** Cached session timeout in seconds (per-request cache, keyed by tenant). */
    private static ?int $cachedSessionTimeout = null;

    /** Tenant ID for which the timeout cache is valid. */
    private static ?string $cachedTimeoutTenantId = null;

    /**
     * Test-only injected timeout value (seconds, already clamped).
     * null = use DB / fallback logic.
     * @internal used by unit tests only
     */
    private static ?int $testSessionTimeout = null;

    /** Tenant ID for which the test timeout override is valid. */
    private static ?string $testTimeoutTenantId = null;

    /** Injectable RepositoryFactory for testing. */
    private static ?RepositoryFactory $repoFactory = null;

    // =========================================================================
    // CONSTRUCTOR (nullable DI for tests)
    // =========================================================================

    public function __construct(?RepositoryFactory $repoFactory = null) {
        if ($repoFactory !== null) {
            self::$repoFactory = $repoFactory;
        }
    }

    // =========================================================================
    // SESSION TIMEOUT
    // =========================================================================

    /**
     * Returns session timeout in seconds for the given tenant.
     *
     * Reads the `settSessionTimeout` key (stored as minutes) from tenant_settings.
     * Value is clamped to 5-480 minutes (300-28800 seconds).
     * Falls back to DEFAULT_SESSION_TIMEOUT (1800 s) when not set or on DB error.
     *
     * PITFALL: Reads tenant_id from $_SESSION directly, NOT from
     * AuthMiddleware::getCurrentTenantId(), to avoid circular dependency.
     */
    public static function getSessionTimeout(?string $tenantId = null): int {
        $tid = $tenantId
            ?? ($_SESSION['auth_user']['tenant_id'] ?? null)
            ?? self::getDefaultTenantId();

        // Test override (injected via setSessionTimeoutForTest)
        if (self::$testSessionTimeout !== null && self::$testTimeoutTenantId === $tid) {
            return max(300, min(28800, self::$testSessionTimeout));
        }

        // Per-request cache
        if (self::$cachedSessionTimeout !== null && self::$cachedTimeoutTenantId === $tid) {
            return self::$cachedSessionTimeout;
        }

        try {
            $repo = self::getRepoFactory()->settings();
            $val = $repo->get($tid, 'settSessionTimeout');
            if ($val !== null && is_numeric($val)) {
                $seconds = ((int) $val) * 60; // stored as minutes, used as seconds
                $seconds = max(300, min(28800, $seconds)); // clamp: 5min - 480min
                self::$cachedSessionTimeout = $seconds;
                self::$cachedTimeoutTenantId = $tid;
                return $seconds;
            }
        } catch (Throwable $e) {
            // DB failure: fall back to default
        }

        self::$cachedSessionTimeout = self::DEFAULT_SESSION_TIMEOUT;
        self::$cachedTimeoutTenantId = $tid;
        return self::DEFAULT_SESSION_TIMEOUT;
    }

    /**
     * Test helper: inject a specific timeout value (seconds) for the given tenant.
     * Pass null to clear the override (use DB logic).
     *
     * @internal used by unit tests only
     */
    public static function setSessionTimeoutForTest(string $tenantId, ?int $seconds): void {
        self::$testSessionTimeout = $seconds;
        self::$testTimeoutTenantId = $tenantId;
        // Also clear the per-request cache so next call hits our injected value
        self::$cachedSessionTimeout = null;
        self::$cachedTimeoutTenantId = null;
    }

    // =========================================================================
    // SESSION EXPIRY
    // =========================================================================

    /**
     * Checks if the session has expired based on last activity time.
     *
     * @return bool True if expired (session should be destroyed)
     */
    public static function checkExpiry(int $lastActivity, ?string $tenantId = null): bool {
        $now = time();
        if ($lastActivity > 0 && ($now - $lastActivity) > self::getSessionTimeout($tenantId)) {
            return true;
        }
        return false;
    }

    /**
     * Returns the revalidation interval constant.
     */
    public static function getRevalidateInterval(): int {
        return self::SESSION_REVALIDATE_INTERVAL;
    }

    /**
     * Re-validates a user against the database.
     *
     * @return array{valid: bool, user: ?array, reason: ?string} Revalidation result
     */
    public static function revalidateUser(string $userId): array {
        try {
            $repo = self::getRepoFactory()->user();
            $fresh = $repo->findForSessionRevalidation($userId);

            if (!$fresh || empty($fresh['is_active'])) {
                return [
                    'valid' => false,
                    'user' => null,
                    'reason' => !$fresh ? 'user_deleted' : 'user_deactivated',
                ];
            }

            return [
                'valid' => true,
                'user' => $fresh,
                'reason' => null,
            ];
        } catch (Throwable $e) {
            // DB failure: keep session alive, try again next interval
            error_log('Session revalidation DB error: ' . $e->getMessage());
            return [
                'valid' => true,
                'user' => null,
                'reason' => 'db_error',
            ];
        }
    }

    // =========================================================================
    // SESSION EXPIRED FLAG
    // =========================================================================

    /** Returns whether the last authenticate() detected an expired session. */
    public static function isSessionExpired(): bool {
        return self::$sessionExpired;
    }

    /** Gets and clears the session expired flag. */
    public static function consumeSessionExpired(): bool {
        $was = self::$sessionExpired;
        self::$sessionExpired = false;
        return $was;
    }

    /** Sets the session expired flag. */
    public static function setSessionExpired(bool $expired): void {
        self::$sessionExpired = $expired;
    }

    // =========================================================================
    // RESET
    // =========================================================================

    /** Clears all static state (for tests). */
    public static function reset(): void {
        self::$sessionExpired = false;
        self::$cachedSessionTimeout = null;
        self::$cachedTimeoutTenantId = null;
        self::$testSessionTimeout = null;
        self::$testTimeoutTenantId = null;
        self::$repoFactory = null;
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    private static function getRepoFactory(): RepositoryFactory {
        return self::$repoFactory ?? RepositoryFactory::getInstance();
    }

    private static function getDefaultTenantId(): string {
        return defined('DEFAULT_TENANT_ID')
            ? DEFAULT_TENANT_ID
            : 'aaaaaaaa-1111-2222-3333-444444444444';
    }
}
