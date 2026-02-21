<?php
declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Repository\UserRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\MemberRepository;
use RuntimeException;

/**
 * AuthMiddleware - Authentication and RBAC Authorization (two-level)
 *
 * Role model:
 *   SYSTEM LEVEL (users.role) - permanent, tied to user account
 *     admin    = Platform super-administrator
 *     operator = Operator (operational management)
 *     auditor  = Auditor (compliance, read-only)
 *     viewer   = Observer (read-only)
 *
 *   MEETING LEVEL (meeting_roles) - temporary, assigned per meeting
 *     president = Meeting president (governance)
 *     assessor  = Assessor/Scrutineer (co-control)
 *     voter     = Voter (voting)
 *
 * Permission resolution:
 *   effective_permissions = permissions(system_role) ∪ permissions(meeting_roles)
 */
final class AuthMiddleware
{
    // =========================================================================
    // CONSTANTS — delegated to Permissions (single source of truth)
    // =========================================================================

    /** System roles (subset of hierarchy with level >= viewer) */
    private const SYSTEM_ROLES = [
        'admin'    => 100,
        'operator' => 80,
        'auditor'  => 50,
        'viewer'   => 10,
    ];

    /** Meeting roles (no hierarchy, distinct permissions) */
    private const MEETING_ROLES = ['president', 'assessor', 'voter'];

    /** Session timeout in seconds (30 minutes) */
    private const SESSION_TIMEOUT = 1800;

    /**
     * Role aliases for backward compatibility.
     */
    private const ROLE_ALIASES = [
        'trust'    => 'assessor',
        'readonly' => 'viewer',
    ];

    // =========================================================================
    // STATE
    // =========================================================================

    private static ?array $currentUser = null;
    private static ?string $currentMeetingId = null;
    private static ?array $currentMeetingRoles = null;
    private static bool $debug = false;
    private static array $accessLog = [];

    // =========================================================================
    // INIT / CONFIG
    // =========================================================================

    public static function init(array $config = []): void
    {
        self::$debug = (bool)($config['debug'] ?? (getenv('APP_DEBUG') === '1'));
    }

    public static function isEnabled(): bool
    {
        $env = getenv('APP_AUTH_ENABLED');
        return $env === '1' || strtolower((string)$env) === 'true';
    }

    // =========================================================================
    // MEETING CONTEXT
    // =========================================================================

    /**
     * Sets the meeting context for role resolution.
     * Must be called before can() / requireRole() when checking
     * permissions related to a specific meeting.
     */
    public static function setMeetingContext(?string $meetingId): void
    {
        if (self::$currentMeetingId !== $meetingId) {
            self::$currentMeetingId = $meetingId;
            self::$currentMeetingRoles = null; // force re-fetch
        }
    }

    /**
     * Returns the meeting roles of the current user
     * for the meeting in context.
     *
     * @return string[] e.g.: ['president'], ['assessor', 'voter'], []
     */
    public static function getMeetingRoles(?string $meetingId = null): array
    {
        $mid = $meetingId ?? self::$currentMeetingId;
        if ($mid === null) {
            return [];
        }

        $user = self::getCurrentUser();
        if (!$user || !isset($user['id'])) {
            return [];
        }

        // Cache for current meeting
        if ($mid === self::$currentMeetingId && self::$currentMeetingRoles !== null) {
            return self::$currentMeetingRoles;
        }

        try {
            $repo = new UserRepository();
            $roles = $repo->listUserRolesForMeeting(
                $user['tenant_id'] ?? self::getDefaultTenantId(),
                $mid,
                $user['id']
            );

            if ($mid === self::$currentMeetingId) {
                self::$currentMeetingRoles = $roles;
            }

            return $roles;
        } catch (\Throwable $e) {
            error_log("getMeetingRoles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns all effective roles of the current user.
     * = system role + meeting roles (if context is set)
     *
     * @return string[] e.g.: ['operator', 'president']
     */
    public static function getEffectiveRoles(?string $meetingId = null): array
    {
        $roles = [];

        $systemRole = self::getCurrentRole();
        if ($systemRole !== 'anonymous') {
            $roles[] = $systemRole;
        }

        $meetingRoles = self::getMeetingRoles($meetingId);
        return array_unique(array_merge($roles, $meetingRoles));
    }

    // =========================================================================
    // ROLE NORMALIZATION
    // =========================================================================

    /**
     * Normalizes a role name (lowercase + alias).
     */
    private static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        return self::ROLE_ALIASES[$role] ?? $role;
    }

    /**
     * Checks if a role is a meeting role.
     */
    public static function isMeetingRole(string $role): bool
    {
        return in_array(self::normalizeRole($role), self::MEETING_ROLES, true);
    }

    /**
     * Checks if a role is a system role.
     */
    public static function isSystemRole(string $role): bool
    {
        return isset(self::SYSTEM_ROLES[self::normalizeRole($role)]);
    }

    // =========================================================================
    // AUTH : requireRole
    // =========================================================================

    /**
     * Requires a role to access the resource.
     *
     * Checks both system role AND meeting roles.
     * If a requested role is a meeting role (president, assessor, voter),
     * checks in meeting_roles for the meeting in context.
     */
    public static function requireRole(string|array $roles, bool $strict = true): bool
    {
        // Bypass if auth disabled (DEV only) - handled in authenticate()
        if (!self::isEnabled()) {
            self::authenticate(); // Sets dev-user as admin
            return true;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        $roles = array_map([self::class, 'normalizeRole'], $roles);

        // 'public' role = no auth required
        if (in_array('public', $roles, true)) {
            return true;
        }

        $user = self::authenticate();
        if ($user === null) {
            if ($strict) {
                self::deny('authentication_required', 401);
            }
            return false;
        }

        $systemRole = self::normalizeRole((string)($user['role'] ?? 'anonymous'));

        // Admin has all rights
        if ($systemRole === 'admin') {
            return true;
        }

        // Check direct system role
        if (in_array($systemRole, $roles, true)) {
            return true;
        }

        // Check by system hierarchy
        $userLevel = Permissions::HIERARCHY[$systemRole] ?? 0;
        foreach ($roles as $requiredRole) {
            // Do not use hierarchy for meeting roles
            if (self::isMeetingRole($requiredRole)) {
                continue;
            }
            $requiredLevel = Permissions::HIERARCHY[$requiredRole] ?? 0;
            if ($userLevel >= $requiredLevel) {
                return true;
            }
        }

        // Check meeting roles (if context is set)
        $meetingRoles = self::getMeetingRoles();
        foreach ($roles as $requiredRole) {
            if (in_array($requiredRole, $meetingRoles, true)) {
                return true;
            }
        }

        if ($strict) {
            self::deny('forbidden', 403, [
                'required_roles' => $roles,
                'user_role' => $systemRole,
                'meeting_roles' => $meetingRoles,
            ]);
        }
        return false;
    }

    // =========================================================================
    // AUTH : authenticate
    // =========================================================================

    public static function authenticate(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        // Bypass if auth disabled (DEV only)
        if (!self::isEnabled()) {
            self::$currentUser = [
                'id' => 'dev-user',
                'role' => 'admin',
                'name' => 'Dev User (Auth Disabled)',
                'tenant_id' => self::getDefaultTenantId(),
            ];
            return self::$currentUser;
        }

        // 1. API Key (header)
        $apiKey = self::extractApiKey();
        if ($apiKey !== null) {
            $user = self::findUserByApiKey($apiKey);
            if ($user !== null) {
                self::$currentUser = $user;
                return $user;
            }
        }

        // 2. Session PHP
        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['auth_user'])) {
                // Check session timeout
                $lastActivity = $_SESSION['auth_last_activity'] ?? 0;
                $now = time();

                if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TIMEOUT) {
                    // Session expired - destroy it
                    error_log(sprintf(
                        'SESSION_EXPIRED | user_id=%s | idle=%ds',
                        $_SESSION['auth_user']['id'] ?? 'unknown',
                        $now - $lastActivity
                    ));
                    $_SESSION = [];
                    session_destroy();
                    return null;
                }

                // Update last activity timestamp
                $_SESSION['auth_last_activity'] = $now;

                self::$currentUser = $_SESSION['auth_user'];
                return self::$currentUser;
            }
        }

        return null;
    }

    // =========================================================================
    // AUTH : can (permission check)
    // =========================================================================

    /**
     * Checks if the current user has a specific permission.
     * Resolves permissions via: system role + meeting roles.
     */
    public static function can(string $permission, ?string $meetingId = null): bool
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $systemRole = self::normalizeRole((string)($user['role'] ?? 'anonymous'));

        // Admin has all permissions
        if ($systemRole === 'admin') {
            return true;
        }

        $allowedRoles = Permissions::PERMISSIONS[$permission] ?? [];

        // Check system role
        if (in_array($systemRole, $allowedRoles, true)) {
            return true;
        }

        // Check system hierarchy
        $userLevel = Permissions::HIERARCHY[$systemRole] ?? 0;
        foreach ($allowedRoles as $allowedRole) {
            if (self::isMeetingRole($allowedRole)) {
                continue; // Don't hierarchy-compare meeting roles
            }
            if ($userLevel >= (Permissions::HIERARCHY[$allowedRole] ?? 0)) {
                return true;
            }
        }

        // Check meeting roles
        $meetingRoles = self::getMeetingRoles($meetingId);
        foreach ($meetingRoles as $mr) {
            if (in_array($mr, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Requires a specific permission
     */
    public static function requirePermission(string $permission, ?string $meetingId = null): void
    {
        if (!self::can($permission, $meetingId)) {
            self::logAccessAttempt($permission, false);
            self::deny('permission_denied', 403, [
                'required_permission' => $permission,
                'user_role' => self::getCurrentRole(),
                'meeting_roles' => self::getMeetingRoles($meetingId),
            ]);
        }
        self::logAccessAttempt($permission, true);
    }

    // =========================================================================
    // CURRENT USER GETTERS
    // =========================================================================

    public static function getCurrentUser(): ?array
    {
        if (self::$currentUser === null) {
            self::authenticate();
        }
        return self::$currentUser;
    }

    public static function getCurrentUserId(): ?string
    {
        $user = self::getCurrentUser();
        return $user ? (string)($user['id'] ?? null) : null;
    }

    /** Returns the SYSTEM role of the current user */
    public static function getCurrentRole(): string
    {
        $user = self::getCurrentUser();
        return self::normalizeRole((string)($user['role'] ?? 'anonymous'));
    }

    public static function getCurrentTenantId(): string
    {
        $user = self::getCurrentUser();
        return (string)($user['tenant_id'] ?? self::getDefaultTenantId());
    }

    // =========================================================================
    // MEETING ACCESS
    // =========================================================================

    public static function canAccessMeeting(string $meetingId, string $action = 'read'): bool
    {
        // Set meeting context for role resolution
        self::setMeetingContext($meetingId);

        if (!self::can("meeting:{$action}", $meetingId)) {
            return false;
        }

        // Tenant check
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $tenantId = $user['tenant_id'] ?? self::getDefaultTenantId();

        try {
            $repo = new MeetingRepository();
            return $repo->findByIdForTenant($meetingId, $tenantId) !== null;
        } catch (\Throwable $e) {
            error_log("Meeting access check error: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // MEETING STATE MACHINE
    // =========================================================================

    /**
     * Checks if the state transition is allowed.
     * 'president' transitions require meeting_role 'president'
     * OR system role 'admin'.
     */
    public static function canTransition(string $fromStatus, string $toStatus, ?string $meetingId = null): bool
    {
        $allowed = Permissions::TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            return false;
        }

        $requiredRole = $allowed[$toStatus];
        $systemRole = self::getCurrentRole();

        // Admin can do everything
        if ($systemRole === 'admin') {
            return true;
        }

        // System role match
        if ($systemRole === $requiredRole) {
            return true;
        }

        // Meeting role match (president transitions)
        $meetingRoles = self::getMeetingRoles($meetingId);
        if (in_array($requiredRole, $meetingRoles, true)) {
            return true;
        }

        return false;
    }

    public static function requireTransition(string $fromStatus, string $toStatus, ?string $meetingId = null): void
    {
        $allowed = Permissions::TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            self::deny('invalid_transition', 422, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'allowed' => array_keys($allowed),
            ]);
        }

        if (!self::canTransition($fromStatus, $toStatus, $meetingId)) {
            self::deny('transition_forbidden', 403, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'required_role' => $allowed[$toStatus],
                'user_role' => self::getCurrentRole(),
                'meeting_roles' => self::getMeetingRoles($meetingId),
            ]);
        }
    }

    public static function availableTransitions(string $currentStatus, ?string $meetingId = null): array
    {
        $all = Permissions::TRANSITIONS[$currentStatus] ?? [];
        $result = [];

        foreach ($all as $to => $requiredRole) {
            if (self::canTransition($currentStatus, $to, $meetingId)) {
                $result[] = ['to' => $to, 'required_role' => $requiredRole];
            }
        }

        return $result;
    }

    // =========================================================================
    // ROLE INFO / LABELS
    // =========================================================================

    public static function getSystemRoles(): array
    {
        return array_keys(self::SYSTEM_ROLES);
    }

    public static function getSystemRoleLabels(): array
    {
        return array_intersect_key(
            Permissions::LABELS['roles'],
            self::SYSTEM_ROLES
        );
    }

    public static function getMeetingRoleLabels(): array
    {
        return array_intersect_key(
            Permissions::LABELS['roles'],
            array_flip(self::MEETING_ROLES)
        );
    }

    public static function getRoleLabels(): array
    {
        return Permissions::LABELS['roles'];
    }

    public static function getMeetingStatusLabels(): array
    {
        return Permissions::LABELS['statuses'];
    }

    public static function getAllRoles(): array
    {
        return array_keys(Permissions::HIERARCHY);
    }

    // =========================================================================
    // PERMISSIONS INFO
    // =========================================================================

    public static function getAvailablePermissions(?string $meetingId = null): array
    {
        $effectiveRoles = self::getEffectiveRoles($meetingId);
        $permissions = [];

        foreach (Permissions::PERMISSIONS as $permission => $allowedRoles) {
            if (self::getCurrentRole() === 'admin') {
                $permissions[] = $permission;
                continue;
            }
            foreach ($effectiveRoles as $role) {
                if (in_array($role, $allowedRoles, true)) {
                    $permissions[] = $permission;
                    break;
                }
            }
        }

        return array_unique($permissions);
    }

    public static function getRoleLevel(string $role): int
    {
        return Permissions::HIERARCHY[self::normalizeRole($role)] ?? 0;
    }

    public static function isRoleAtLeast(string $role, string $minimumRole): bool
    {
        return self::getRoleLevel($role) >= self::getRoleLevel($minimumRole);
    }

    // =========================================================================
    // INTERNAL : API Key, auth, logging
    // =========================================================================

    private static function extractApiKey(): ?string
    {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'X-Api-Key') === 0 || strcasecmp($k, 'X-API-KEY') === 0) {
                    $key = (string)$v;
                    break;
                }
            }
        }
        $key = trim($key);
        return $key !== '' ? $key : null;
    }

    private static function findUserByApiKey(string $apiKey): ?array
    {
        $secret = self::getAppSecret();
        $hash = hash_hmac('sha256', $apiKey, $secret);

        try {
            $repo = new UserRepository();
            $row = $repo->findByApiKeyHashGlobal($hash);

            if (!$row) {
                self::logAuthFailure('invalid_api_key', $apiKey);
                return null;
            }

            if (!$row['is_active']) {
                self::logAuthFailure('user_inactive', $apiKey);
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            error_log("API key lookup error: " . $e->getMessage());
            return null;
        }
    }

    public static function generateApiKey(): array
    {
        $key = bin2hex(random_bytes(32));
        $hash = hash_hmac('sha256', $key, self::getAppSecret());
        return ['key' => $key, 'hash' => $hash];
    }

    public static function hashApiKey(string $key): string
    {
        return hash_hmac('sha256', $key, self::getAppSecret());
    }

    private static function deny(string $code, int $httpCode = 401, array $extra = []): never
    {
        self::logAuthFailure($code);

        $body = ['ok' => false, 'error' => $code];
        if (self::$debug && !empty($extra)) {
            $body['debug'] = $extra;
        }

        throw new \AgVote\Core\Http\ApiResponseException(
            new \AgVote\Core\Http\JsonResponse($httpCode, $body, [
                'WWW-Authenticate' => 'ApiKey realm="AG-Vote API"',
            ])
        );
    }

    private static function logAuthFailure(string $reason, ?string $credential = null): void
    {
        error_log(sprintf(
            "AUTH_FAILURE | reason=%s | ip=%s | uri=%s",
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
    }

    private static function logAccessAttempt(string $resource, bool $granted): void
    {
        $user = self::getCurrentUser();
        self::$accessLog[] = [
            'timestamp' => date('c'),
            'user_id' => $user['id'] ?? null,
            'user_role' => $user['role'] ?? 'anonymous',
            'resource' => $resource,
            'granted' => $granted,
        ];

        if (!$granted) {
            error_log(sprintf(
                "ACCESS_DENIED | user=%s | role=%s | resource=%s | uri=%s",
                $user['id'] ?? 'anonymous',
                $user['role'] ?? 'anonymous',
                $resource,
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            ));
        }
    }

    public static function getAccessLog(): array
    {
        return self::$accessLog;
    }

    public static function isOwner(string $resourceType, string $resourceId): bool
    {
        $user = self::getCurrentUser();
        $userId = $user['id'] ?? null;
        if (!$userId) return false;

        try {
            return match($resourceType) {
                'meeting' => (new MeetingRepository())->isOwnedByUser($resourceId, $userId),
                'motion'  => (new MotionRepository())->isOwnedByUser($resourceId, $userId),
                'member'  => (new MemberRepository())->isOwnedByUser($resourceId, $userId),
                default   => false,
            };
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function getAppSecret(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');

        // Strict validation: secret required if auth enabled or production
        if (!$secret || $secret === 'change-me-in-prod' || strlen($secret) < 32) {
            if (self::isEnabled()) {
                throw new \RuntimeException(
                    '[SECURITY] APP_SECRET must be set to a secure value (min 32 characters). ' .
                    'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
                );
            }
            // In dev mode only, log a warning
            error_log('[WARNING] Using insecure APP_SECRET in dev mode. Do NOT use in production.');
            return 'dev-secret-not-for-production-' . str_repeat('x', 32);
        }

        return $secret;
    }

    private static function getDefaultTenantId(): string
    {
        return defined('DEFAULT_TENANT_ID')
            ? DEFAULT_TENANT_ID
            : 'aaaaaaaa-1111-2222-3333-444444444444';
    }

    // =========================================================================
    // TEST HELPERS
    // =========================================================================

    public static function setCurrentUser(?array $user): void
    {
        self::$currentUser = $user;
    }

    public static function reset(): void
    {
        self::$currentUser = null;
        self::$currentMeetingId = null;
        self::$currentMeetingRoles = null;
        self::$accessLog = [];
    }
}
