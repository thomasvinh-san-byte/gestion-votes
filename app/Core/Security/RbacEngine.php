<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Providers\RepositoryFactory;
use Throwable;

/**
 * RbacEngine - Role-based access control evaluation.
 *
 * Extracted from AuthMiddleware. All methods that need the current user
 * receive $user as a parameter (injected by AuthMiddleware delegation stubs).
 */
final class RbacEngine {
    private const SYSTEM_ROLES = ['admin', 'operator', 'auditor', 'viewer', 'president'];
    private const MEETING_ROLES = ['president', 'assessor', 'voter'];
    private const ROLE_ALIASES = ['trust' => 'assessor', 'readonly' => 'viewer'];

    private static ?string $currentMeetingId = null;
    private static ?array $currentMeetingRoles = null;
    private static ?RepositoryFactory $repoFactory = null;

    public function __construct(?RepositoryFactory $repoFactory = null) {
        if ($repoFactory !== null) {
            self::$repoFactory = $repoFactory;
        }
    }

    public static function normalizeRole(string $role): string {
        $role = strtolower(trim($role));
        return self::ROLE_ALIASES[$role] ?? $role;
    }

    public static function isMeetingRole(string $role): bool {
        return in_array(self::normalizeRole($role), self::MEETING_ROLES, true);
    }

    public static function isSystemRole(string $role): bool {
        return in_array(self::normalizeRole($role), self::SYSTEM_ROLES, true);
    }

    public static function setMeetingContext(?string $meetingId): void {
        if (self::$currentMeetingId !== $meetingId) {
            self::$currentMeetingId = $meetingId;
            self::$currentMeetingRoles = null;
        }
    }

    /** @return string[] Meeting roles for the user in context */
    public static function getMeetingRoles(?array $user, ?string $meetingId = null): array {
        $mid = $meetingId ?? self::$currentMeetingId;
        if ($mid === null || !$user || !isset($user['id'])) {
            return [];
        }

        if ($mid === self::$currentMeetingId && self::$currentMeetingRoles !== null) {
            return self::$currentMeetingRoles;
        }

        try {
            $repo = self::getRepoFactory()->user();
            $defaultTenant = defined('DEFAULT_TENANT_ID') ? DEFAULT_TENANT_ID : 'aaaaaaaa-1111-2222-3333-444444444444';
            $roles = $repo->listUserRolesForMeeting($user['tenant_id'] ?? $defaultTenant, $mid, $user['id']);
            if ($mid === self::$currentMeetingId) {
                self::$currentMeetingRoles = $roles;
            }
            return $roles;
        } catch (Throwable $e) {
            error_log('getMeetingRoles error: ' . $e->getMessage());
            return [];
        }
    }

    /** @return string[] System role + meeting roles */
    public static function getEffectiveRoles(?array $user, ?string $meetingId = null): array {
        $roles = [];
        $systemRole = self::normalizeRole((string) ($user['role'] ?? 'anonymous'));
        if ($systemRole !== 'anonymous') {
            $roles[] = $systemRole;
        }
        return array_unique(array_merge($roles, self::getMeetingRoles($user, $meetingId)));
    }

    /** Checks if user satisfies any of the required roles (system + hierarchy + meeting). */
    public static function checkRole(?array $user, array $roles): bool {
        if (!$user) {
            return false;
        }
        $systemRole = self::normalizeRole((string) ($user['role'] ?? 'anonymous'));
        if ($systemRole === 'admin') {
            return true;
        }
        if (in_array($systemRole, $roles, true)) {
            return true;
        }
        $userLevel = Permissions::HIERARCHY[$systemRole] ?? 0;
        foreach ($roles as $requiredRole) {
            if (self::isMeetingRole($requiredRole)) {
                continue;
            }
            if ($userLevel >= (Permissions::HIERARCHY[$requiredRole] ?? 0)) {
                return true;
            }
        }
        $meetingRoles = self::getMeetingRoles($user);
        foreach ($roles as $requiredRole) {
            if (in_array($requiredRole, $meetingRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /** Checks if user has a specific permission. */
    public static function can(?array $user, string $permission, ?string $meetingId = null): bool {
        if (!$user) {
            return false;
        }
        $systemRole = self::normalizeRole((string) ($user['role'] ?? 'anonymous'));
        if ($systemRole === 'admin') {
            return true;
        }
        $allowedRoles = Permissions::PERMISSIONS[$permission] ?? [];
        if (in_array($systemRole, $allowedRoles, true)) {
            return true;
        }
        $userLevel = Permissions::HIERARCHY[$systemRole] ?? 0;
        foreach ($allowedRoles as $allowedRole) {
            if (self::isMeetingRole($allowedRole)) {
                continue;
            }
            if ($userLevel >= Permissions::HIERARCHY[$allowedRole]) {
                return true;
            }
        }
        $meetingRoles = self::getMeetingRoles($user, $meetingId);
        foreach ($meetingRoles as $mr) {
            if (in_array($mr, $allowedRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /** Checks if user can access a meeting (permission + tenant check). */
    public static function canAccessMeeting(?array $user, string $meetingId): bool {
        self::setMeetingContext($meetingId);
        if (!self::can($user, 'meeting:read', $meetingId) || !$user) {
            return false;
        }
        $defaultTenant = defined('DEFAULT_TENANT_ID') ? DEFAULT_TENANT_ID : 'aaaaaaaa-1111-2222-3333-444444444444';
        $tenantId = $user['tenant_id'] ?? $defaultTenant;
        try {
            return self::getRepoFactory()->meeting()->findByIdForTenant($meetingId, $tenantId) !== null;
        } catch (Throwable $e) {
            error_log('Meeting access check error: ' . $e->getMessage());
            return false;
        }
    }

    /** Checks if a state transition is allowed for the user. */
    public static function canTransition(?array $user, string $fromStatus, string $toStatus, ?string $meetingId = null): bool {
        $allowed = Permissions::TRANSITIONS[$fromStatus] ?? [];
        if (!isset($allowed[$toStatus])) {
            return false;
        }
        $requiredRoles = (array) $allowed[$toStatus];
        $systemRole = self::normalizeRole((string) ($user['role'] ?? 'anonymous'));
        if ($systemRole === 'admin') {
            return true;
        }
        if (in_array($systemRole, $requiredRoles, true)) {
            return true;
        }
        $meetingRoles = self::getMeetingRoles($user, $meetingId);
        foreach ($requiredRoles as $r) {
            if (in_array($r, $meetingRoles, true)) {
                return true;
            }
        }
        return false;
    }

    /** Returns available transitions for a user from the current status. */
    public static function availableTransitions(?array $user, string $currentStatus, ?string $meetingId = null): array {
        $all = Permissions::TRANSITIONS[$currentStatus] ?? [];
        $result = [];
        foreach ($all as $to => $requiredRole) {
            if (self::canTransition($user, $currentStatus, $to, $meetingId)) {
                $result[] = ['to' => $to, 'required_roles' => (array) $requiredRole];
            }
        }
        return $result;
    }

    /** Returns all permissions available to a user. */
    public static function getAvailablePermissions(?array $user, ?string $meetingId = null): array {
        $effectiveRoles = self::getEffectiveRoles($user, $meetingId);
        $systemRole = self::normalizeRole((string) ($user['role'] ?? 'anonymous'));
        $permissions = [];
        foreach (Permissions::PERMISSIONS as $permission => $allowedRoles) {
            if ($systemRole === 'admin') {
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

    public static function getRoleLevel(string $role): int {
        return Permissions::HIERARCHY[self::normalizeRole($role)] ?? 0;
    }

    public static function isRoleAtLeast(string $role, string $minimumRole): bool {
        return self::getRoleLevel($role) >= self::getRoleLevel($minimumRole);
    }

    public static function getSystemRoles(): array {
        return self::SYSTEM_ROLES;
    }

    public static function getSystemRoleLabels(): array {
        return array_intersect_key(Permissions::LABELS['roles'], array_flip(self::SYSTEM_ROLES));
    }

    public static function getMeetingRoleLabels(): array {
        return array_intersect_key(Permissions::LABELS['roles'], array_flip(self::MEETING_ROLES));
    }

    public static function getRoleLabels(): array {
        return Permissions::LABELS['roles'];
    }

    public static function getMeetingStatusLabels(): array {
        return Permissions::LABELS['statuses'];
    }

    public static function getAllRoles(): array {
        return array_keys(Permissions::HIERARCHY);
    }

    public static function reset(): void {
        self::$currentMeetingId = null;
        self::$currentMeetingRoles = null;
        self::$repoFactory = null;
    }

    private static function getRepoFactory(): RepositoryFactory {
        return self::$repoFactory ?? RepositoryFactory::getInstance();
    }
}
