<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les utilisateurs (users).
 */
class UserRepository extends AbstractRepository
{
    /**
     * Trouve un utilisateur par hash de cle API.
     */
    public function findByApiKeyHash(string $tenantId, string $hash): ?array
    {
        return $this->selectOne(
            "SELECT id, tenant_id, email, name, role, is_active
             FROM users
             WHERE tenant_id = :tid AND api_key_hash = :hash
             LIMIT 1",
            [':tid' => $tenantId, ':hash' => $hash]
        );
    }

    /**
     * Trouve un utilisateur par hash de cle API (sans filtre tenant, phase auth).
     */
    public function findByApiKeyHashGlobal(string $hash): ?array
    {
        return $this->selectOne(
            "SELECT id, tenant_id, email, name, role, is_active
             FROM users
             WHERE api_key_hash = :hash
             LIMIT 1",
            [':hash' => $hash]
        );
    }

    /**
     * Liste les noms de roles actifs d'un utilisateur pour une seance donnee.
     */
    public function listUserRolesForMeeting(string $tenantId, string $meetingId, string $userId): array
    {
        return array_column(
            $this->selectAll(
                "SELECT role FROM meeting_roles
                 WHERE tenant_id = :tid AND meeting_id = :mid AND user_id = :uid
                   AND revoked_at IS NULL",
                [':tid' => $tenantId, ':mid' => $meetingId, ':uid' => $userId]
            ),
            'role'
        );
    }

    /**
     * Liste toutes les permissions par role (table role_permissions).
     */
    public function listRolePermissions(): array
    {
        return $this->selectAll(
            "SELECT role, permission, description FROM role_permissions ORDER BY role, permission"
        );
    }

    /**
     * Compte les utilisateurs actifs par role systeme pour un tenant.
     */
    public function countBySystemRole(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT role, COUNT(*) as count FROM users WHERE tenant_id = :tid AND is_active = true GROUP BY role ORDER BY role",
            [':tid' => $tenantId]
        );
    }

    /**
     * Compte les assignations actives par role de seance pour un tenant.
     */
    public function countByMeetingRole(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT role, COUNT(DISTINCT user_id) as users, COUNT(DISTINCT meeting_id) as meetings
             FROM meeting_roles WHERE tenant_id = :tid AND revoked_at IS NULL GROUP BY role ORDER BY role",
            [':tid' => $tenantId]
        );
    }

    /**
     * Enregistre un echec d'authentification (best-effort).
     */
    public function logAuthFailure(string $ip, string $userAgent, string $keyPrefix, string $reason = 'invalid_key'): void
    {
        $this->execute(
            "INSERT INTO auth_failures (ip, user_agent, key_prefix, reason, created_at)
             VALUES (:ip, :ua, :prefix, :reason, NOW())",
            [
                ':ip' => $ip,
                ':ua' => substr($userAgent, 0, 200),
                ':prefix' => $keyPrefix,
                ':reason' => $reason,
            ]
        );
    }

    // =========================================================================
    // ADMIN USERS CRUD
    // =========================================================================

    /**
     * Liste les utilisateurs d'un tenant avec filtre optionnel par role.
     */
    public function listByTenant(string $tenantId, ?string $roleFilter = null): array
    {
        $params = [':tid' => $tenantId];
        $where = "WHERE u.tenant_id = :tid";
        if ($roleFilter !== null && $roleFilter !== '') {
            $where .= " AND u.role = :role";
            $params[':role'] = $roleFilter;
        }
        return $this->selectAll(
            "SELECT u.id, u.email, u.name, u.role, u.is_active, u.created_at, u.updated_at,
                    CASE WHEN u.api_key_hash IS NOT NULL THEN true ELSE false END AS has_api_key
             FROM users u
             {$where}
             ORDER BY u.role ASC, u.name ASC",
            $params
        );
    }

    /**
     * Liste les roles de seance actifs d'un utilisateur.
     */
    public function listActiveMeetingRolesForUser(string $userId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT mr.role, mr.meeting_id, m.title AS meeting_title
             FROM meeting_roles mr
             JOIN meetings m ON m.id = mr.meeting_id
             WHERE mr.user_id = :uid AND mr.tenant_id = :tid AND mr.revoked_at IS NULL
             ORDER BY mr.assigned_at DESC",
            [':uid' => $userId, ':tid' => $tenantId]
        );
    }

    /**
     * Met a jour le hash de cle API d'un utilisateur (rotation).
     */
    public function rotateApiKey(string $tenantId, string $userId, string $hash): void
    {
        $this->execute(
            "UPDATE users SET api_key_hash = :h, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':h' => $hash, ':t' => $tenantId, ':id' => $userId]
        );
    }

    /**
     * Supprime la cle API d'un utilisateur (revocation).
     */
    public function revokeApiKey(string $tenantId, string $userId): void
    {
        $this->execute(
            "UPDATE users SET api_key_hash = NULL, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':t' => $tenantId, ':id' => $userId]
        );
    }

    /**
     * Active ou desactive un utilisateur.
     */
    public function toggleActive(string $tenantId, string $userId, bool $isActive): void
    {
        $this->execute(
            "UPDATE users SET is_active = :a, updated_at = NOW() WHERE tenant_id = :t AND id = :id",
            [':a' => $isActive ? 'true' : 'false', ':t' => $tenantId, ':id' => $userId]
        );
    }

    /**
     * Met a jour les champs d'un utilisateur.
     */
    public function updateUser(string $tenantId, string $userId, string $email, string $name, ?string $role = null): void
    {
        $setClauses = ["email = :e", "name = :n", "updated_at = NOW()"];
        $params = [':e' => $email, ':n' => $name, ':t' => $tenantId, ':id' => $userId];
        if ($role !== null && $role !== '') {
            $setClauses[] = "role = :r";
            $params[':r'] = $role;
        }
        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE tenant_id = :t AND id = :id";
        $this->execute($sql, $params);
    }

    /**
     * Retourne l'ID d'un utilisateur par email (pour verifier unicite).
     */
    public function findIdByEmail(string $tenantId, string $email): ?string
    {
        $val = $this->scalar(
            "SELECT id FROM users WHERE tenant_id = :t AND email = :e",
            [':t' => $tenantId, ':e' => $email]
        );
        return $val !== false && $val !== null ? (string)$val : null;
    }

    /**
     * Genere un UUID via PostgreSQL.
     */
    public function newUuid(): string
    {
        return $this->generateUuid();
    }

    /**
     * Cree un nouvel utilisateur.
     */
    public function createUser(
        string $id,
        string $tenantId,
        string $email,
        string $name,
        string $role,
        string $apiKeyHash
    ): void {
        $this->execute(
            "INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
             VALUES (:id, :t, :e, :n, :r, :h, true, NOW(), NOW())",
            [':id' => $id, ':t' => $tenantId, ':e' => $email, ':n' => $name, ':r' => $role, ':h' => $apiKeyHash]
        );
    }

    // =========================================================================
    // MEETING ROLES
    // =========================================================================

    /**
     * Liste les roles assignes pour une seance (avec infos utilisateur et assignateur).
     */
    public function listMeetingRolesForMeeting(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT mr.id, mr.user_id, mr.role, mr.assigned_at, mr.revoked_at,
                    u.name AS user_name, u.email AS user_email, u.role AS system_role,
                    a.name AS assigned_by_name
             FROM meeting_roles mr
             JOIN users u ON u.id = mr.user_id
             LEFT JOIN users a ON a.id = mr.assigned_by
             WHERE mr.tenant_id = :t AND mr.meeting_id = :m AND mr.revoked_at IS NULL
             ORDER BY mr.role ASC, u.name ASC",
            [':t' => $tenantId, ':m' => $meetingId]
        );
    }

    /**
     * Resume des roles par seance (toutes les seances avec des roles assignes).
     */
    public function listMeetingRolesSummary(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT m.id AS meeting_id, m.title, m.status,
                    json_agg(json_build_object(
                        'user_id', mr.user_id,
                        'user_name', u.name,
                        'role', mr.role
                    ) ORDER BY mr.role, u.name) AS roles
             FROM meeting_roles mr
             JOIN meetings m ON m.id = mr.meeting_id
             JOIN users u ON u.id = mr.user_id
             WHERE mr.tenant_id = :t AND mr.revoked_at IS NULL
             GROUP BY m.id, m.title, m.status
             ORDER BY m.title",
            [':t' => $tenantId]
        );
    }

    /**
     * Trouve un utilisateur actif par ID et tenant (id, name).
     */
    public function findActiveById(string $userId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, name FROM users WHERE id = :id AND tenant_id = :t AND is_active = true",
            [':id' => $userId, ':t' => $tenantId]
        );
    }

    /**
     * Trouve le user_id du president actuel d'une seance.
     */
    public function findExistingPresident(string $tenantId, string $meetingId): ?string
    {
        $val = $this->scalar(
            "SELECT user_id FROM meeting_roles
             WHERE tenant_id = :t AND meeting_id = :m AND role = 'president' AND revoked_at IS NULL",
            [':t' => $tenantId, ':m' => $meetingId]
        );
        return $val !== false && $val !== null ? (string)$val : null;
    }

    /**
     * Revoque le role de president pour une seance.
     */
    public function revokePresidentRole(string $tenantId, string $meetingId): void
    {
        $this->execute(
            "UPDATE meeting_roles SET revoked_at = NOW()
             WHERE tenant_id = :t AND meeting_id = :m AND role = 'president' AND revoked_at IS NULL",
            [':t' => $tenantId, ':m' => $meetingId]
        );
    }

    /**
     * Assigne un role de seance (upsert).
     */
    public function assignMeetingRole(
        string $tenantId,
        string $meetingId,
        string $userId,
        string $role,
        string $assignedBy
    ): void {
        $this->execute(
            "INSERT INTO meeting_roles (tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
             VALUES (:t, :m, :u, :r, :by, NOW())
             ON CONFLICT (tenant_id, meeting_id, user_id, role) DO UPDATE
             SET revoked_at = NULL, assigned_by = :by2, assigned_at = NOW()",
            [
                ':t' => $tenantId,
                ':m' => $meetingId,
                ':u' => $userId,
                ':r' => $role,
                ':by' => $assignedBy,
                ':by2' => $assignedBy,
            ]
        );
    }

    /**
     * Revoque un role de seance (tous les roles si role est null).
     */
    public function revokeMeetingRole(string $tenantId, string $meetingId, string $userId, ?string $role = null): void
    {
        $where = "tenant_id = :t AND meeting_id = :m AND user_id = :u AND revoked_at IS NULL";
        $params = [':t' => $tenantId, ':m' => $meetingId, ':u' => $userId];
        if ($role !== null && $role !== '') {
            $where .= " AND role = :r";
            $params[':r'] = $role;
        }
        $this->execute(
            "UPDATE meeting_roles SET revoked_at = NOW() WHERE $where",
            $params
        );
    }

    // =========================================================================
    // SYSTEM STATUS
    // =========================================================================

    /**
     * Ping la base de donnees et retourne la latence en ms (ou null si erreur).
     */
    public function dbPing(): ?float
    {
        $t0 = microtime(true);
        try {
            $this->scalar("SELECT 1");
            return (microtime(true) - $t0) * 1000.0;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retourne le nombre de connexions actives (ou null si erreur).
     */
    public function dbActiveConnections(): ?int
    {
        try {
            $val = $this->scalar("SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()");
            return $val !== null ? (int)$val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compte les evenements d'audit d'un tenant.
     */
    public function countAuditEvents(string $tenantId): ?int
    {
        try {
            return (int)($this->scalar(
                "SELECT COUNT(*) FROM audit_events WHERE tenant_id = :t",
                [':t' => $tenantId]
            ) ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compte les echecs d'authentification des 15 dernieres minutes.
     */
    public function countAuthFailures15m(): ?int
    {
        try {
            return (int)($this->scalar(
                "SELECT COUNT(*) FROM auth_failures WHERE created_at > NOW() - INTERVAL '15 minutes'"
            ) ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Insere une ligne dans system_metrics.
     */
    public function insertSystemMetric(array $data): void
    {
        $this->execute(
            "INSERT INTO system_metrics(server_time, db_latency_ms, db_active_connections, disk_free_bytes, disk_total_bytes, count_meetings, count_motions, count_vote_tokens, count_audit_events, auth_failures_15m)
             VALUES (:st,:lat,:ac,:free,:tot,:cm,:cmo,:ct,:ca,:af)",
            [
                ':st' => $data['server_time'],
                ':lat' => $data['db_latency_ms'],
                ':ac' => $data['db_active_connections'],
                ':free' => $data['disk_free_bytes'],
                ':tot' => $data['disk_total_bytes'],
                ':cm' => $data['count_meetings'],
                ':cmo' => $data['count_motions'],
                ':ct' => $data['count_vote_tokens'],
                ':ca' => $data['count_audit_events'],
                ':af' => $data['auth_failures_15m'],
            ]
        );
    }

    /**
     * Verifie si une alerte systeme recente existe (10 min) pour un code donne.
     */
    public function findRecentAlert(string $code): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM system_alerts WHERE code = :c AND created_at > NOW() - INTERVAL '10 minutes' LIMIT 1",
            [':c' => $code]
        );
    }

    /**
     * Insere une alerte systeme.
     */
    public function insertSystemAlert(string $code, string $severity, string $message, ?string $detailsJson): void
    {
        $this->execute(
            "INSERT INTO system_alerts(code, severity, message, details_json, created_at) VALUES (:c,:s,:m,:d,NOW())",
            [':c' => $code, ':s' => $severity, ':m' => $message, ':d' => $detailsJson]
        );
    }

    /**
     * Liste les alertes systeme recentes.
     */
    public function listRecentAlerts(int $limit = 20): array
    {
        try {
            return $this->selectAll(
                "SELECT id, created_at, code, severity, message, details_json FROM system_alerts ORDER BY created_at DESC LIMIT " . max(1, $limit)
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
