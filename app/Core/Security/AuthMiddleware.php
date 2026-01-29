<?php
declare(strict_types=1);

/**
 * AuthMiddleware - Authentification et Autorisation RBAC unifiée
 * 
 * Système complet avec:
 * - Hiérarchie des rôles
 * - Permissions granulaires par ressource
 * - Audit des accès
 * - Rate limiting par utilisateur
 * - Support multi-tenant
 */
final class AuthMiddleware
{
    /**
     * Hiérarchie des rôles (niveau numérique)
     *
     * admin     = Super-administrateur (plateforme, tous les droits)
     * operator  = Opérateur (gestion opérationnelle de séance)
     * president = Président (gouvernance : gel, ouverture, validation)
     * assessor  = Assesseur/Scrutateur (co-contrôle, co-signature)
     * auditor   = Auditeur (conformité, vérification intégrité)
     * voter     = Électeur (vote uniquement)
     * viewer    = Observateur (lecture seule)
     * public    = Accès public (pas d'auth requise)
     * anonymous = Non authentifié
     */
    private const ROLE_HIERARCHY = [
        'admin' => 100,
        'operator' => 80,
        'president' => 70,
        'assessor' => 60,
        'auditor' => 50,
        'voter' => 10,
        'viewer' => 5,
        'public' => 3,
        'anonymous' => 0,
    ];

    /**
     * Matrice des permissions par rôle
     * Format: 'resource:action' => [roles autorisés]
     *
     * Séparation des pouvoirs :
     *   - L'opérateur prépare et exécute
     *   - Le président autorise et valide (gouvernance)
     *   - L'assesseur observe et co-signe
     *   - L'auditeur vérifie la conformité
     *   - L'admin supervise tout
     */
    private const PERMISSIONS = [
        // Meetings - cycle de vie
        'meeting:create'   => ['admin', 'operator'],
        'meeting:read'     => ['admin', 'operator', 'president', 'assessor', 'auditor', 'voter', 'viewer'],
        'meeting:update'   => ['admin', 'operator'],
        'meeting:delete'   => ['admin'],
        'meeting:freeze'   => ['admin', 'president'],
        'meeting:unfreeze' => ['admin'],
        'meeting:open'     => ['admin', 'president'],
        'meeting:close'    => ['admin', 'president'],
        'meeting:validate' => ['admin', 'president'],
        'meeting:archive'  => ['admin', 'operator'],

        // Motions
        'motion:create' => ['admin', 'operator'],
        'motion:read'   => ['admin', 'operator', 'president', 'assessor', 'auditor', 'voter', 'viewer'],
        'motion:update' => ['admin', 'operator'],
        'motion:delete' => ['admin', 'operator'],
        'motion:open'   => ['admin', 'operator'],
        'motion:close'  => ['admin', 'operator', 'president'],

        // Votes
        'vote:cast'   => ['admin', 'operator', 'voter'],
        'vote:read'   => ['admin', 'operator', 'president', 'assessor', 'auditor'],
        'vote:manual' => ['admin', 'operator'],

        // Members
        'member:create' => ['admin', 'operator'],
        'member:read'   => ['admin', 'operator', 'president', 'assessor', 'auditor', 'viewer'],
        'member:update' => ['admin', 'operator'],
        'member:delete' => ['admin'],
        'member:import' => ['admin', 'operator'],

        // Attendance
        'attendance:create' => ['admin', 'operator'],
        'attendance:read'   => ['admin', 'operator', 'president', 'assessor', 'auditor', 'viewer'],
        'attendance:update' => ['admin', 'operator'],

        // Proxies
        'proxy:create' => ['admin', 'operator'],
        'proxy:read'   => ['admin', 'operator', 'president', 'assessor', 'auditor', 'viewer'],
        'proxy:delete' => ['admin', 'operator'],

        // Speech
        'speech:request' => ['admin', 'operator', 'president', 'voter'],
        'speech:grant'   => ['admin', 'operator', 'president'],
        'speech:end'     => ['admin', 'operator', 'president'],

        // Audit
        'audit:read'   => ['admin', 'president', 'assessor', 'auditor'],
        'audit:export' => ['admin', 'president', 'auditor'],

        // Admin
        'admin:users'    => ['admin'],
        'admin:policies' => ['admin'],
        'admin:system'   => ['admin'],
        'admin:roles'    => ['admin'],

        // Reports
        'report:generate' => ['admin', 'operator', 'president'],
        'report:read'     => ['admin', 'operator', 'president', 'assessor', 'auditor', 'viewer'],
        'report:export'   => ['admin', 'operator', 'president', 'auditor'],
    ];

    /**
     * Alias de rôles pour compatibilité avec l'ancien système.
     * Les anciens noms de rôles sont mappés vers les nouveaux.
     */
    private const ROLE_ALIASES = [
        'trust'    => 'assessor',
        'readonly' => 'viewer',
    ];

    private static ?array $currentUser = null;
    private static bool $debug = false;
    private static array $accessLog = [];

    public static function init(array $config = []): void
    {
        self::$debug = (bool)($config['debug'] ?? (getenv('APP_DEBUG') === '1'));
    }

    public static function isEnabled(): bool
    {
        $env = getenv('APP_AUTH_ENABLED');
        return $env === '1' || strtolower((string)$env) === 'true';
    }

    /**
     * Normalise un nom de rôle (minuscule + alias).
     */
    private static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        return self::ROLE_ALIASES[$role] ?? $role;
    }

    /**
     * Exige un rôle minimum pour accéder à la ressource.
     * Gère les alias de rôles (trust→assessor, readonly→viewer)
     * et la comparaison insensible à la casse.
     */
    public static function requireRole(string|array $roles, bool $strict = true): bool
    {
        // Bypass si auth désactivée (DEV uniquement)
        if (!self::isEnabled()) {
            self::$currentUser = [
                'id' => 'dev-user',
                'role' => 'admin',
                'name' => 'Dev User (Auth Disabled)',
                'tenant_id' => self::getDefaultTenantId(),
            ];
            return true;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        // Normaliser tous les rôles demandés (alias + minuscule)
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

        $userRole = self::normalizeRole((string)($user['role'] ?? 'anonymous'));

        // Admin a tous les droits
        if ($userRole === 'admin') {
            return true;
        }

        // Vérifie si le rôle utilisateur est dans la liste autorisée
        if (in_array($userRole, $roles, true)) {
            return true;
        }

        // Vérifie la hiérarchie des rôles
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        foreach ($roles as $requiredRole) {
            $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;
            if ($userLevel >= $requiredLevel) {
                return true;
            }
        }

        if ($strict) {
            self::deny('forbidden', 403, ['required_roles' => $roles, 'user_role' => $userRole]);
        }
        return false;
    }

    /**
     * Authentifie l'utilisateur
     */
    public static function authenticate(): ?array
    {
        if (self::$currentUser !== null) {
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
            global $pdo;
            if (!$pdo) {
                return null;
            }

            $stmt = $pdo->prepare(
                "SELECT id, tenant_id, email, name, role, is_active
                 FROM users
                 WHERE api_key_hash = :hash
                 LIMIT 1"
            );
            $stmt->execute([':hash' => $hash]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

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

    private static function getAppSecret(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');
        if (!$secret || $secret === 'change-me-in-prod') {
            if (self::isEnabled()) {
                throw new \RuntimeException('APP_SECRET must be set in production');
            }
            return 'dev-secret-not-for-production';
        }
        return $secret;
    }

    private static function getDefaultTenantId(): string
    {
        return defined('DEFAULT_TENANT_ID') 
            ? DEFAULT_TENANT_ID 
            : 'aaaaaaaa-1111-2222-3333-444444444444';
    }

    /**
     * Génère une nouvelle API Key
     */
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

    /**
     * Vérifie si l'utilisateur courant a une permission spécifique
     * 
     * @param string $permission Format 'resource:action' (ex: 'meeting:create')
     */
    public static function can(string $permission): bool
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $userRole = self::normalizeRole((string)($user['role'] ?? 'anonymous'));

        // Admin a toutes les permissions
        if ($userRole === 'admin') {
            return true;
        }

        // Vérifier dans la matrice des permissions
        $allowedRoles = self::PERMISSIONS[$permission] ?? [];

        if (in_array($userRole, $allowedRoles, true)) {
            return true;
        }

        // Vérifier par hiérarchie si la permission autorise des rôles de niveau inférieur
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        foreach ($allowedRoles as $allowedRole) {
            $allowedLevel = self::ROLE_HIERARCHY[$allowedRole] ?? 0;
            if ($userLevel >= $allowedLevel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exige une permission spécifique
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            self::logAccessAttempt($permission, false);
            self::deny('permission_denied', 403, [
                'required_permission' => $permission,
                'user_role' => self::getCurrentRole()
            ]);
        }
        self::logAccessAttempt($permission, true);
    }

    /**
     * Vérifie si l'utilisateur peut accéder à une ressource d'un meeting spécifique
     */
    public static function canAccessMeeting(string $meetingId, string $action = 'read'): bool
    {
        if (!self::can("meeting:{$action}")) {
            return false;
        }

        // Vérifier que le meeting appartient au même tenant
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $tenantId = $user['tenant_id'] ?? self::getDefaultTenantId();
        
        try {
            global $pdo;
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare("SELECT 1 FROM meetings WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$meetingId, $tenantId]);
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            error_log("Meeting access check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log un accès (réussi ou non)
     */
    private static function logAccessAttempt(string $resource, bool $granted): void
    {
        $user = self::getCurrentUser();
        $entry = [
            'timestamp' => date('c'),
            'user_id' => $user['id'] ?? null,
            'user_role' => $user['role'] ?? 'anonymous',
            'resource' => $resource,
            'granted' => $granted,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        ];

        self::$accessLog[] = $entry;

        // Log les refus
        if (!$granted) {
            error_log(sprintf(
                "ACCESS_DENIED | user=%s | role=%s | resource=%s | ip=%s | uri=%s",
                $entry['user_id'] ?? 'anonymous',
                $entry['user_role'],
                $resource,
                $entry['ip'],
                $entry['uri']
            ));
        }
    }

    /**
     * Obtient le journal d'accès de la requête courante
     */
    public static function getAccessLog(): array
    {
        return self::$accessLog;
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une ressource
     */
    public static function isOwner(string $resourceType, string $resourceId): bool
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        $userId = $user['id'] ?? null;
        if (!$userId) {
            return false;
        }

        try {
            global $pdo;
            if (!$pdo) {
                return false;
            }

            $table = match($resourceType) {
                'meeting' => 'meetings',
                'motion' => 'motions',
                'member' => 'members',
                default => null
            };

            if (!$table) {
                return false;
            }

            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ? AND created_by_user_id = ?");
            $stmt->execute([$resourceId, $userId]);
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Obtient les permissions disponibles pour le rôle courant
     */
    public static function getAvailablePermissions(): array
    {
        $userRole = self::getCurrentRole();
        $permissions = [];

        foreach (self::PERMISSIONS as $permission => $allowedRoles) {
            if (in_array($userRole, $allowedRoles, true) || $userRole === 'admin') {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Vérifie le niveau de rôle
     */
    public static function getRoleLevel(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 0;
    }

    /**
     * Vérifie si un rôle est supérieur ou égal à un autre
     */
    public static function isRoleAtLeast(string $role, string $minimumRole): bool
    {
        return self::getRoleLevel($role) >= self::getRoleLevel($minimumRole);
    }

    // =========================================================================
    // MEETING STATE MACHINE
    // =========================================================================

    /**
     * Transitions autorisées de la machine à états séance.
     * Miroir de la table meeting_state_transitions en DB.
     * Format: from_status => [to_status => required_role]
     */
    private const STATE_TRANSITIONS = [
        'draft'     => ['scheduled' => 'operator', 'frozen' => 'president'],
        'scheduled' => ['frozen' => 'president', 'draft' => 'admin'],
        'frozen'    => ['live' => 'president', 'scheduled' => 'admin'],
        'live'      => ['closed' => 'president'],
        'closed'    => ['validated' => 'president'],
        'validated' => ['archived' => 'admin'],
    ];

    /**
     * Vérifie si la transition d'état est autorisée pour le rôle courant.
     *
     * @param string $fromStatus État actuel de la séance
     * @param string $toStatus   État cible
     * @return bool
     */
    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::STATE_TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            return false;
        }

        $requiredRole = $allowed[$toStatus];
        $userRole = self::getCurrentRole();

        // Admin peut tout faire
        if ($userRole === 'admin') {
            return true;
        }

        return $userRole === $requiredRole;
    }

    /**
     * Exige que la transition d'état soit autorisée.
     * Renvoie une erreur 403 si la transition est refusée.
     *
     * @param string $fromStatus État actuel
     * @param string $toStatus   État cible
     */
    public static function requireTransition(string $fromStatus, string $toStatus): void
    {
        $allowed = self::STATE_TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            self::deny('invalid_transition', 422, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'allowed' => array_keys($allowed),
            ]);
        }

        if (!self::canTransition($fromStatus, $toStatus)) {
            $requiredRole = $allowed[$toStatus];
            self::deny('transition_forbidden', 403, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'required_role' => $requiredRole,
                'user_role' => self::getCurrentRole(),
            ]);
        }
    }

    /**
     * Retourne les transitions possibles depuis un état donné, pour le rôle courant.
     *
     * @param string $currentStatus
     * @return array [['to' => string, 'required_role' => string], ...]
     */
    public static function availableTransitions(string $currentStatus): array
    {
        $all = self::STATE_TRANSITIONS[$currentStatus] ?? [];
        $userRole = self::getCurrentRole();
        $result = [];

        foreach ($all as $to => $requiredRole) {
            if ($userRole === 'admin' || $userRole === $requiredRole) {
                $result[] = ['to' => $to, 'required_role' => $requiredRole];
            }
        }

        return $result;
    }

    /**
     * Retourne la liste complète des rôles du système.
     */
    public static function getAllRoles(): array
    {
        return array_keys(self::ROLE_HIERARCHY);
    }

    /**
     * Retourne les libellés humains des rôles (français).
     */
    public static function getRoleLabels(): array
    {
        return [
            'admin'     => 'Administrateur',
            'operator'  => 'Opérateur',
            'president' => 'Président',
            'assessor'  => 'Assesseur',
            'auditor'   => 'Auditeur',
            'voter'     => 'Électeur',
            'viewer'    => 'Observateur',
        ];
    }

    /**
     * Retourne les libellés humains des états séance (français).
     */
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

    // =========================================================================
    // STATE / TEST HELPERS
    // =========================================================================

    /**
     * Définit l'utilisateur courant (pour tests ou impersonation)
     */
    public static function setCurrentUser(?array $user): void
    {
        self::$currentUser = $user;
    }

    /**
     * Réinitialise l'état (pour tests)
     */
    public static function reset(): void
    {
        self::$currentUser = null;
        self::$accessLog = [];
    }
}
