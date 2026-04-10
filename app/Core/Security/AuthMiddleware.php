<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RepositoryFactory;
use RuntimeException;
use Throwable;

/**
 * AuthMiddleware - Thin orchestrator delegating to SessionManager and RbacEngine.
 * Preserves the full public static API so zero callers need updating.
 */
final class AuthMiddleware {
    private static ?array $currentUser = null;
    private static ?string $currentMeetingId = null;
    private static ?array $currentMeetingRoles = null;
    private static bool $debug = false;
    private static array $accessLog = [];
    private static bool $sessionExpired = false;
    private static ?int $cachedSessionTimeout = null;
    private static ?string $cachedTimeoutTenantId = null;
    private static ?int $testSessionTimeout = null;
    private static ?string $testTimeoutTenantId = null;

    public static function init(array $config = []): void {
        self::$debug = (bool) ($config['debug'] ?? (getenv('APP_DEBUG') === '1'));
    }

    public static function isEnabled(): bool {
        $env = getenv('APP_AUTH_ENABLED');
        return !($env === '0' || strtolower((string) $env) === 'false');
    }

    public static function getSessionTimeout(?string $tenantId = null): int {
        return SessionManager::getSessionTimeout($tenantId);
    }

    public static function setSessionTimeoutForTest(string $tenantId, ?int $seconds): void {
        SessionManager::setSessionTimeoutForTest($tenantId, $seconds);
        self::$testSessionTimeout = $seconds;
        self::$testTimeoutTenantId = $tenantId;
        self::$cachedSessionTimeout = null;
        self::$cachedTimeoutTenantId = null;
    }

    public static function authenticate(): ?array {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        if (!self::isEnabled()) {
            self::$currentUser = ['id' => 'dev-user', 'role' => 'admin', 'name' => 'Dev User (Auth Disabled)', 'tenant_id' => self::getDefaultTenantId()];
            return self::$currentUser;
        }
        $apiKey = self::extractApiKey();
        if ($apiKey !== null) {
            $user = self::findUserByApiKey($apiKey);
            if ($user !== null) {
                self::$currentUser = $user;
                return $user;
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
            SessionHelper::start();
            if (!empty($_SESSION['auth_user'])) {
                $lastActivity = $_SESSION['auth_last_activity'] ?? 0;
                $now = time();
                if (SessionManager::checkExpiry($lastActivity)) {
                    error_log(sprintf('SESSION_EXPIRED | user_id=%s | idle=%ds', $_SESSION['auth_user']['id'] ?? 'unknown', $now - $lastActivity));
                    $_SESSION = [];
                    session_destroy();
                    self::$sessionExpired = true;
                    SessionManager::setSessionExpired(true);
                    return null;
                }
                $lastDbCheck = $_SESSION['auth_last_db_check'] ?? 0;
                if (($now - $lastDbCheck) >= SessionManager::getRevalidateInterval()) {
                    $result = SessionManager::revalidateUser((string) ($_SESSION['auth_user']['id'] ?? ''));
                    if (!$result['valid'] && $result['reason'] !== 'db_error') {
                        error_log(sprintf('SESSION_REVOKED | user_id=%s | reason=%s', $_SESSION['auth_user']['id'] ?? 'unknown', $result['reason']));
                        $_SESSION = [];
                        session_destroy();
                        return null;
                    }
                    if ($result['user'] !== null) {
                        $prev = $_SESSION['auth_user']['role'] ?? '';
                        $_SESSION['auth_user']['role'] = $result['user']['role'];
                        $_SESSION['auth_user']['name'] = $result['user']['name'];
                        $_SESSION['auth_user']['email'] = $result['user']['email'];
                        $_SESSION['auth_user']['is_active'] = $result['user']['is_active'];
                        if ($result['user']['role'] !== $prev) { session_regenerate_id(true); }
                    }
                    $_SESSION['auth_last_db_check'] = $now;
                }
                $_SESSION['auth_last_activity'] = $now;
                self::$currentUser = $_SESSION['auth_user'];
                return self::$currentUser;
            }
        }
        return null;
    }

    public static function getCurrentUser(): ?array {
        if (self::$currentUser === null) { self::authenticate(); }
        return self::$currentUser;
    }
    public static function getCurrentUserId(): ?string {
        $u = self::getCurrentUser();
        return $u ? (string) ($u['id'] ?? null) : null;
    }
    public static function getCurrentRole(): string {
        $u = self::getCurrentUser();
        return RbacEngine::normalizeRole((string) ($u['role'] ?? 'anonymous'));
    }
    public static function getCurrentTenantId(): string {
        $u = self::getCurrentUser();
        return (string) ($u['tenant_id'] ?? self::getDefaultTenantId());
    }

    public static function requireRole(string|array $roles, bool $strict = true): bool {
        if (!self::isEnabled()) { self::authenticate(); return true; }
        $roles = is_array($roles) ? $roles : [$roles];
        $roles = array_map([RbacEngine::class, 'normalizeRole'], $roles);
        if (in_array('public', $roles, true)) { return true; }
        $user = self::authenticate();
        if ($user === null) {
            if ($strict) { self::deny('authentication_required', 401); }
            return false;
        }
        if (RbacEngine::checkRole($user, $roles)) { return true; }
        if ($strict) {
            self::deny('forbidden', 403, [
                'required_roles' => $roles,
                'user_role' => RbacEngine::normalizeRole((string) ($user['role'] ?? 'anonymous')),
                'meeting_roles' => RbacEngine::getMeetingRoles($user),
            ]);
        }
        return false;
    }

    public static function can(string $permission, ?string $meetingId = null): bool {
        return RbacEngine::can(self::getCurrentUser(), $permission, $meetingId);
    }
    public static function requirePermission(string $permission, ?string $meetingId = null): void {
        if (!self::can($permission, $meetingId)) {
            self::logAccessAttempt($permission, false);
            self::deny('permission_denied', 403, ['required_permission' => $permission, 'user_role' => self::getCurrentRole(), 'meeting_roles' => self::getMeetingRoles($meetingId)]);
        }
        self::logAccessAttempt($permission, true);
    }
    public static function canAccessMeeting(string $meetingId, string $action = 'read'): bool {
        RbacEngine::setMeetingContext($meetingId);
        if (!RbacEngine::can(self::getCurrentUser(), "meeting:{$action}", $meetingId)) { return false; }
        $user = self::getCurrentUser();
        if (!$user) { return false; }
        try {
            return RepositoryFactory::getInstance()->meeting()->findByIdForTenant($meetingId, $user['tenant_id'] ?? self::getDefaultTenantId()) !== null;
        } catch (Throwable $e) { error_log('Meeting access check error: ' . $e->getMessage()); return false; }
    }
    public static function canTransition(string $from, string $to, ?string $meetingId = null): bool {
        return RbacEngine::canTransition(self::getCurrentUser(), $from, $to, $meetingId);
    }
    public static function requireTransition(string $from, string $to, ?string $meetingId = null): void {
        $allowed = Permissions::TRANSITIONS[$from] ?? [];
        if (!isset($allowed[$to])) {
            api_fail('invalid_transition', 422, ['detail' => "Transition '{$from}' \u{2192} '{$to}' non autoris\u{e9}e.", 'from_status' => $from, 'to_status' => $to, 'allowed' => array_keys($allowed)]);
        }
        if (!self::canTransition($from, $to, $meetingId)) {
            self::deny('transition_forbidden', 403, ['from' => $from, 'to' => $to, 'required_roles' => (array) ($allowed[$to] ?? []), 'user_role' => self::getCurrentRole(), 'meeting_roles' => self::getMeetingRoles($meetingId)]);
        }
    }
    public static function availableTransitions(string $status, ?string $meetingId = null): array {
        return RbacEngine::availableTransitions(self::getCurrentUser(), $status, $meetingId);
    }
    public static function setMeetingContext(?string $meetingId): void {
        RbacEngine::setMeetingContext($meetingId);
        self::$currentMeetingId = $meetingId;
        self::$currentMeetingRoles = null;
    }
    public static function getMeetingRoles(?string $meetingId = null): array {
        return RbacEngine::getMeetingRoles(self::getCurrentUser(), $meetingId);
    }
    public static function getEffectiveRoles(?string $meetingId = null): array {
        return RbacEngine::getEffectiveRoles(self::getCurrentUser(), $meetingId);
    }
    public static function normalizeRole(string $role): string { return RbacEngine::normalizeRole($role); }
    public static function isMeetingRole(string $role): bool { return RbacEngine::isMeetingRole($role); }
    public static function isSystemRole(string $role): bool { return RbacEngine::isSystemRole($role); }
    public static function getAvailablePermissions(?string $meetingId = null): array {
        return RbacEngine::getAvailablePermissions(self::getCurrentUser(), $meetingId);
    }
    public static function getRoleLevel(string $role): int { return RbacEngine::getRoleLevel($role); }
    public static function isRoleAtLeast(string $role, string $min): bool { return RbacEngine::isRoleAtLeast($role, $min); }
    public static function getSystemRoles(): array { return RbacEngine::getSystemRoles(); }
    public static function getSystemRoleLabels(): array { return RbacEngine::getSystemRoleLabels(); }
    public static function getMeetingRoleLabels(): array { return RbacEngine::getMeetingRoleLabels(); }
    public static function getRoleLabels(): array { return RbacEngine::getRoleLabels(); }
    public static function getMeetingStatusLabels(): array { return RbacEngine::getMeetingStatusLabels(); }
    public static function getAllRoles(): array { return RbacEngine::getAllRoles(); }

    private static function extractApiKey(): ?string {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'X-Api-Key') === 0 || strcasecmp($k, 'X-API-KEY') === 0) { $key = (string) $v; break; }
            }
        }
        return ($key = trim($key)) !== '' ? $key : null;
    }
    private static function findUserByApiKey(string $apiKey): ?array {
        try { $secret = self::getAppSecret(); } catch (Throwable $e) { error_log('API key auth unavailable: ' . $e->getMessage()); return null; }
        $hash = hash_hmac('sha256', $apiKey, $secret);
        try {
            $row = RepositoryFactory::getInstance()->user()->findByApiKeyHashGlobal($hash);
            if (!$row) { self::logAuthFailure('invalid_api_key', $apiKey); return null; }
            if (!$row['is_active']) { self::logAuthFailure('user_inactive', $apiKey); return null; }
            return $row;
        } catch (Throwable $e) { error_log('API key lookup error: ' . $e->getMessage()); return null; }
    }
    public static function generateApiKey(): array {
        $key = bin2hex(random_bytes(32));
        return ['key' => $key, 'hash' => hash_hmac('sha256', $key, self::getAppSecret())];
    }
    public static function hashApiKey(string $key): string { return hash_hmac('sha256', $key, self::getAppSecret()); }

    private static function deny(string $code, int $httpCode = 401, array $extra = []): never {
        if ($code === 'authentication_required' && self::$sessionExpired) { $code = 'session_expired'; self::$sessionExpired = false; }
        self::logAuthFailure($code);
        $body = ['ok' => false, 'error' => $code];
        if (self::$debug && !empty($extra)) { $body['debug'] = $extra; }
        throw new \AgVote\Core\Http\ApiResponseException(new \AgVote\Core\Http\JsonResponse($httpCode, $body, ['WWW-Authenticate' => 'ApiKey realm="AG-Vote API"']));
    }
    private static function logAuthFailure(string $reason, ?string $credential = null): void {
        error_log(sprintf('AUTH_FAILURE | reason=%s | ip=%s | uri=%s', $reason, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['REQUEST_URI'] ?? 'unknown'));
    }
    private static function logAccessAttempt(string $resource, bool $granted): void {
        $user = self::getCurrentUser();
        self::$accessLog[] = ['timestamp' => date('c'), 'user_id' => $user['id'] ?? null, 'user_role' => $user['role'] ?? 'anonymous', 'resource' => $resource, 'granted' => $granted];
        if (!$granted) {
            error_log(sprintf('ACCESS_DENIED | user=%s | role=%s | resource=%s | uri=%s', $user['id'] ?? 'anonymous', $user['role'] ?? 'anonymous', $resource, $_SERVER['REQUEST_URI'] ?? 'unknown'));
        }
    }
    public static function getAccessLog(): array { return self::$accessLog; }
    public static function isOwner(string $resourceType, string $resourceId): bool {
        $userId = (self::getCurrentUser())['id'] ?? null;
        if (!$userId) { return false; }
        try {
            $rf = RepositoryFactory::getInstance();
            return match($resourceType) { 'meeting' => $rf->meeting()->isOwnedByUser($resourceId, $userId), 'motion' => $rf->motion()->isOwnedByUser($resourceId, $userId), 'member' => $rf->member()->isOwnedByUser($resourceId, $userId), default => false };
        } catch (Throwable) { return false; }
    }
    private static function getAppSecret(): string {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');
        if (!$secret || strlen($secret) < 32) {
            throw new RuntimeException('[SECURITY] APP_SECRET must be set to a secure value (min 32 characters). Generate one with: php -r "echo bin2hex(random_bytes(32));"');
        }
        return $secret;
    }
    private static function getDefaultTenantId(): string {
        return defined('DEFAULT_TENANT_ID') ? DEFAULT_TENANT_ID : 'aaaaaaaa-1111-2222-3333-444444444444';
    }
    public static function setCurrentUser(?array $user): void { self::$currentUser = $user; }
    public static function reset(): void {
        self::$currentUser = null;
        self::$currentMeetingId = null;
        self::$currentMeetingRoles = null;
        self::$accessLog = [];
        self::$sessionExpired = false;
        self::$cachedSessionTimeout = null;
        self::$cachedTimeoutTenantId = null;
        self::$testSessionTimeout = null;
        self::$testTimeoutTenantId = null;
        SessionManager::reset();
        RbacEngine::reset();
    }
}
