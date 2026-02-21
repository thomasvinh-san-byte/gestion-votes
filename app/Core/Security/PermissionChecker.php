<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Repository\UserRepository;
use Throwable;

/**
 * PermissionChecker - RBAC permission verification service.
 *
 * Encapsulates permission logic in a testable service.
 * Delegates to AuthMiddleware but provides a cleaner API.
 */
class PermissionChecker {
    private array $permissions;
    private array $transitions;
    private array $roleHierarchy;

    public function __construct() {
        $this->permissions = Permissions::PERMISSIONS;
        $this->transitions = Permissions::TRANSITIONS;
        $this->roleHierarchy = Permissions::HIERARCHY;
    }

    /**
     * Verifie si un utilisateur a une permission.
     */
    public function check(array $user, string $permission, ?string $meetingId = null): bool {
        $systemRole = $this->normalizeRole($user['role'] ?? 'anonymous');

        // Admin a toutes les permissions
        if ($systemRole === 'admin') {
            return true;
        }

        $allowedRoles = $this->permissions[$permission] ?? [];
        if (empty($allowedRoles)) {
            return false;
        }

        // Check role systeme direct
        if (in_array($systemRole, $allowedRoles, true)) {
            return true;
        }

        // Check hierarchie
        $userLevel = $this->roleHierarchy[$systemRole] ?? 0;
        foreach ($allowedRoles as $role) {
            if ($this->isMeetingRole($role)) {
                continue;
            }
            if ($userLevel >= ($this->roleHierarchy[$role] ?? 0)) {
                return true;
            }
        }

        // Check roles de seance
        $meetingRoles = $this->getMeetingRoles($user, $meetingId);
        foreach ($meetingRoles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifie si une transition d'etat est autorisee.
     */
    public function canTransition(array $user, string $from, string $to, ?string $meetingId = null): bool {
        $allowed = $this->transitions[$from] ?? [];
        if (!isset($allowed[$to])) {
            return false;
        }

        $requiredRole = $allowed[$to];
        $systemRole = $this->normalizeRole($user['role'] ?? 'anonymous');

        // Admin peut tout faire
        if ($systemRole === 'admin') {
            return true;
        }

        // Match role systeme
        if ($systemRole === $requiredRole) {
            return true;
        }

        // Match role de seance
        $meetingRoles = $this->getMeetingRoles($user, $meetingId);
        return in_array($requiredRole, $meetingRoles, true);
    }

    /**
     * Retourne les transitions disponibles pour un etat donne.
     */
    public function availableTransitions(array $user, string $currentStatus, ?string $meetingId = null): array {
        $all = $this->transitions[$currentStatus] ?? [];
        $result = [];

        foreach ($all as $to => $requiredRole) {
            if ($this->canTransition($user, $currentStatus, $to, $meetingId)) {
                $result[] = [
                    'to' => $to,
                    'required_role' => $requiredRole,
                ];
            }
        }

        return $result;
    }

    /**
     * Verifie si l'utilisateur a au moins un des roles requis.
     */
    public function hasRole(array $user, array $roles, ?string $meetingId = null): bool {
        $systemRole = $this->normalizeRole($user['role'] ?? 'anonymous');

        // Admin a tous les roles
        if ($systemRole === 'admin') {
            return true;
        }

        // Check role systeme direct
        if (in_array($systemRole, $roles, true)) {
            return true;
        }

        // Check hierarchie
        $userLevel = $this->roleHierarchy[$systemRole] ?? 0;
        foreach ($roles as $role) {
            if ($this->isMeetingRole($role)) {
                continue;
            }
            if ($userLevel >= ($this->roleHierarchy[$role] ?? 0)) {
                return true;
            }
        }

        // Check roles de seance
        $meetingRoles = $this->getMeetingRoles($user, $meetingId);
        foreach ($roles as $role) {
            if (in_array($role, $meetingRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne toutes les permissions d'un utilisateur.
     */
    public function getPermissions(array $user, ?string $meetingId = null): array {
        $result = [];
        foreach (array_keys($this->permissions) as $permission) {
            if ($this->check($user, $permission, $meetingId)) {
                $result[] = $permission;
            }
        }
        return $result;
    }

    /**
     * Normalise un nom de role.
     */
    private function normalizeRole(string $role): string {
        $role = strtolower(trim($role));
        $aliases = [
            'trust' => 'assessor',
            'readonly' => 'viewer',
        ];
        return $aliases[$role] ?? $role;
    }

    /**
     * Verifie si un role est un role de seance.
     */
    private function isMeetingRole(string $role): bool {
        return in_array($role, ['president', 'assessor', 'voter'], true);
    }

    /**
     * Recupere les roles de seance d'un utilisateur.
     */
    private function getMeetingRoles(array $user, ?string $meetingId): array {
        if ($meetingId === null || !isset($user['id'])) {
            return [];
        }

        try {
            $repo = new UserRepository();
            $tenantId = $user['tenant_id'] ?? $this->getDefaultTenantId();
            return $repo->listUserRolesForMeeting($tenantId, $meetingId, $user['id']);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Retourne le tenant par defaut.
     */
    private function getDefaultTenantId(): string {
        return defined('DEFAULT_TENANT_ID')
            ? DEFAULT_TENANT_ID
            : 'aaaaaaaa-1111-2222-3333-444444444444';
    }

    /**
     * Retourne la configuration des permissions.
     */
    public function getConfig(): array {
        return [
            'permissions' => $this->permissions,
            'transitions' => $this->transitions,
            'hierarchy' => $this->roleHierarchy,
        ];
    }
}
