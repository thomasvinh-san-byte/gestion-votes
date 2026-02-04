<?php
declare(strict_types=1);

/**
 * AuthMiddleware - Authentification et Autorisation RBAC à deux niveaux
 *
 * Modèle de rôles :
 *   NIVEAU SYSTÈME (users.role) — permanent, lié au compte utilisateur
 *     admin    = Super-administrateur plateforme
 *     operator = Opérateur (gestion opérationnelle)
 *     auditor  = Auditeur (conformité, lecture)
 *     viewer   = Observateur (lecture seule)
 *
 *   NIVEAU SÉANCE (meeting_roles) — temporaire, attribué par séance
 *     president = Président de séance (gouvernance)
 *     assessor  = Assesseur/Scrutateur (co-contrôle)
 *     voter     = Électeur (vote)
 *
 * Résolution des permissions :
 *   permissions_effectives = permissions(system_role) ∪ permissions(meeting_roles)
 */
final class AuthMiddleware
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /** Rôles système avec niveau hiérarchique */
    private const SYSTEM_ROLES = [
        'admin'    => 100,
        'operator' => 80,
        'auditor'  => 50,
        'viewer'   => 10,
    ];

    /** Rôles de séance (pas de hiérarchie, permissions distinctes) */
    private const MEETING_ROLES = ['president', 'assessor', 'voter'];

    /** Niveaux hiérarchiques pour TOUS les rôles (système + séance) */
    private const ROLE_HIERARCHY = [
        'admin'     => 100,
        'operator'  => 80,
        'president' => 70,
        'assessor'  => 60,
        'auditor'   => 50,
        'voter'     => 10,
        'viewer'    => 5,
        'public'    => 3,
        'anonymous' => 0,
    ];

    /**
     * Matrice des permissions par rôle (système ET séance)
     * Format: 'resource:action' => [roles autorisés]
     */
    private const PERMISSIONS = [
        // Meetings - cycle de vie
        'meeting:create'       => ['admin', 'operator'],
        'meeting:read'         => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor', 'voter'],
        'meeting:update'       => ['admin', 'operator'],
        'meeting:delete'       => ['admin'],
        'meeting:freeze'       => ['admin', 'president'],
        'meeting:unfreeze'     => ['admin'],
        'meeting:open'         => ['admin', 'president'],
        'meeting:close'        => ['admin', 'president'],
        'meeting:validate'     => ['admin', 'president'],
        'meeting:archive'      => ['admin', 'operator'],
        'meeting:assign_roles' => ['admin', 'operator'],

        // Motions
        'motion:create' => ['admin', 'operator'],
        'motion:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor', 'voter'],
        'motion:update' => ['admin', 'operator'],
        'motion:delete' => ['admin', 'operator'],
        'motion:open'   => ['admin', 'operator'],
        'motion:close'  => ['admin', 'operator', 'president'],

        // Votes
        'vote:cast'   => ['admin', 'operator', 'voter'],
        'vote:read'   => ['admin', 'operator', 'auditor', 'president', 'assessor'],
        'vote:manual' => ['admin', 'operator'],

        // Members
        'member:create' => ['admin', 'operator'],
        'member:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'member:update' => ['admin', 'operator'],
        'member:delete' => ['admin'],
        'member:import' => ['admin', 'operator'],

        // Attendance
        'attendance:create' => ['admin', 'operator'],
        'attendance:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'attendance:update' => ['admin', 'operator'],

        // Proxies
        'proxy:create' => ['admin', 'operator'],
        'proxy:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'proxy:delete' => ['admin', 'operator'],

        // Speech
        'speech:request' => ['admin', 'operator', 'president', 'voter'],
        'speech:grant'   => ['admin', 'operator', 'president'],
        'speech:end'     => ['admin', 'operator', 'president'],

        // Audit
        'audit:read'   => ['admin', 'auditor', 'president', 'assessor'],
        'audit:export' => ['admin', 'auditor', 'president'],

        // Admin
        'admin:users'    => ['admin'],
        'admin:policies' => ['admin'],
        'admin:system'   => ['admin'],
        'admin:roles'    => ['admin'],

        // Reports
        'report:generate' => ['admin', 'operator', 'president'],
        'report:read'     => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'report:export'   => ['admin', 'operator', 'auditor', 'president'],
    ];

    /**
     * Alias de rôles pour compatibilité ascendante.
     * Les anciens noms de rôles sont mappés vers des rôles système ou séance.
     */
    private const ROLE_ALIASES = [
        'trust'    => 'assessor',
        'readonly' => 'viewer',
    ];

    /** Transitions d'état autorisées : from => [to => required_role] */
    private const STATE_TRANSITIONS = [
        'draft'     => ['scheduled' => 'operator', 'frozen' => 'president'],
        'scheduled' => ['frozen' => 'president', 'draft' => 'admin'],
        'frozen'    => ['live' => 'president', 'scheduled' => 'admin'],
        'live'      => ['closed' => 'president'],
        'closed'    => ['validated' => 'president'],
        'validated' => ['archived' => 'admin'],
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
     * Définit le contexte de séance pour la résolution des rôles.
     * Doit être appelé avant can() / requireRole() quand on vérifie
     * des permissions liées à une séance spécifique.
     */
    public static function setMeetingContext(?string $meetingId): void
    {
        if (self::$currentMeetingId !== $meetingId) {
            self::$currentMeetingId = $meetingId;
            self::$currentMeetingRoles = null; // force re-fetch
        }
    }

    /**
     * Retourne les rôles de séance de l'utilisateur courant
     * pour la séance en contexte.
     *
     * @return string[] ex: ['president'], ['assessor', 'voter'], []
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

        // Cache pour le meeting courant
        if ($mid === self::$currentMeetingId && self::$currentMeetingRoles !== null) {
            return self::$currentMeetingRoles;
        }

        try {
            $repo = new \AgVote\Repository\UserRepository();
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
     * Retourne tous les rôles effectifs de l'utilisateur courant.
     * = rôle système + rôles de séance (si contexte défini)
     *
     * @return string[] ex: ['operator', 'president']
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
     * Normalise un nom de rôle (minuscule + alias).
     */
    private static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        return self::ROLE_ALIASES[$role] ?? $role;
    }

    /**
     * Vérifie si un rôle est un rôle de séance.
     */
    public static function isMeetingRole(string $role): bool
    {
        return in_array(self::normalizeRole($role), self::MEETING_ROLES, true);
    }

    /**
     * Vérifie si un rôle est un rôle système.
     */
    public static function isSystemRole(string $role): bool
    {
        return isset(self::SYSTEM_ROLES[self::normalizeRole($role)]);
    }

    // =========================================================================
    // AUTH : requireRole
    // =========================================================================

    /**
     * Exige un rôle pour accéder à la ressource.
     *
     * Vérifie à la fois le rôle système ET les rôles de séance.
     * Si un rôle demandé est un rôle de séance (president, assessor, voter),
     * on vérifie dans meeting_roles pour la séance en contexte.
     */
    public static function requireRole(string|array $roles, bool $strict = true): bool
    {
        // Bypass si auth désactivée (DEV uniquement) - handled in authenticate()
        if (!self::isEnabled()) {
            self::authenticate(); // Sets dev-user as admin
            return true;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        $roles = array_map([self::class, 'normalizeRole'], $roles);

        // Rôle 'public' = pas d'auth requise
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

        // Admin a tous les droits
        if ($systemRole === 'admin') {
            return true;
        }

        // Vérifier le rôle système direct
        if (in_array($systemRole, $roles, true)) {
            return true;
        }

        // Vérifier par hiérarchie système
        $userLevel = self::ROLE_HIERARCHY[$systemRole] ?? 0;
        foreach ($roles as $requiredRole) {
            // Ne pas utiliser la hiérarchie pour les rôles de séance
            if (self::isMeetingRole($requiredRole)) {
                continue;
            }
            $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;
            if ($userLevel >= $requiredLevel) {
                return true;
            }
        }

        // Vérifier les rôles de séance (si contexte défini)
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

        // Bypass si auth désactivée (DEV uniquement)
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
     * Vérifie si l'utilisateur courant a une permission spécifique.
     * Résout les permissions via : rôle système + rôles séance.
     */
    public static function can(string $permission, ?string $meetingId = null): bool
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $systemRole = self::normalizeRole((string)($user['role'] ?? 'anonymous'));

        // Admin a toutes les permissions
        if ($systemRole === 'admin') {
            return true;
        }

        $allowedRoles = self::PERMISSIONS[$permission] ?? [];

        // Check rôle système
        if (in_array($systemRole, $allowedRoles, true)) {
            return true;
        }

        // Check hiérarchie système
        $userLevel = self::ROLE_HIERARCHY[$systemRole] ?? 0;
        foreach ($allowedRoles as $allowedRole) {
            if (self::isMeetingRole($allowedRole)) {
                continue; // Don't hierarchy-compare meeting roles
            }
            if ($userLevel >= (self::ROLE_HIERARCHY[$allowedRole] ?? 0)) {
                return true;
            }
        }

        // Check rôles séance
        $meetingRoles = self::getMeetingRoles($meetingId);
        foreach ($meetingRoles as $mr) {
            if (in_array($mr, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exige une permission spécifique
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

    /** Retourne le rôle SYSTÈME de l'utilisateur courant */
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
            $repo = new \AgVote\Repository\MeetingRepository();
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
     * Vérifie si la transition d'état est autorisée.
     * Les transitions 'president' requièrent le meeting_role 'president'
     * OU le rôle système 'admin'.
     */
    public static function canTransition(string $fromStatus, string $toStatus, ?string $meetingId = null): bool
    {
        $allowed = self::STATE_TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            return false;
        }

        $requiredRole = $allowed[$toStatus];
        $systemRole = self::getCurrentRole();

        // Admin peut tout faire
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
        $allowed = self::STATE_TRANSITIONS[$fromStatus] ?? [];
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
        $all = self::STATE_TRANSITIONS[$currentStatus] ?? [];
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
        return [
            'admin'    => 'Administrateur',
            'operator' => 'Opérateur',
            'auditor'  => 'Auditeur',
            'viewer'   => 'Observateur',
        ];
    }

    public static function getMeetingRoleLabels(): array
    {
        return [
            'president' => 'Président de séance',
            'assessor'  => 'Assesseur / Scrutateur',
            'voter'     => 'Électeur',
        ];
    }

    public static function getRoleLabels(): array
    {
        return self::getSystemRoleLabels() + self::getMeetingRoleLabels();
    }

    public static function getMeetingStatusLabels(): array
    {
        return [
            'draft'     => 'Brouillon',
            'scheduled' => 'Planifiée',
            'frozen'    => 'Verrouillée',
            'live'      => 'En cours',
            'closed'    => 'Clôturée',
            'validated' => 'Validée',
            'archived'  => 'Archivée',
        ];
    }

    public static function getAllRoles(): array
    {
        return array_keys(self::ROLE_HIERARCHY);
    }

    // =========================================================================
    // PERMISSIONS INFO
    // =========================================================================

    public static function getAvailablePermissions(?string $meetingId = null): array
    {
        $effectiveRoles = self::getEffectiveRoles($meetingId);
        $permissions = [];

        foreach (self::PERMISSIONS as $permission => $allowedRoles) {
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
        return self::ROLE_HIERARCHY[self::normalizeRole($role)] ?? 0;
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
            $repo = new \AgVote\Repository\UserRepository();
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

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: ApiKey realm="AG-Vote API"');

        $response = ['ok' => false, 'error' => $code];
        if (self::$debug && !empty($extra)) {
            $response['debug'] = $extra;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
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
                'meeting' => (new \AgVote\Repository\MeetingRepository())->isOwnedByUser($resourceId, $userId),
                'motion'  => (new \AgVote\Repository\MotionRepository())->isOwnedByUser($resourceId, $userId),
                'member'  => (new \AgVote\Repository\MemberRepository())->isOwnedByUser($resourceId, $userId),
                default   => false,
            };
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function getAppSecret(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');

        // Validation stricte : secret requis si auth activée ou production
        if (!$secret || $secret === 'change-me-in-prod' || strlen($secret) < 32) {
            if (self::isEnabled()) {
                throw new \RuntimeException(
                    '[SECURITY] APP_SECRET must be set to a secure value (min 32 characters). ' .
                    'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
                );
            }
            // En mode dev uniquement, log un warning
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
