<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les groupes de membres.
 *
 * Gere les operations CRUD sur les tables member_groups et member_group_assignments.
 * Permet de regrouper les membres par categories, colleges electoraux, departements, etc.
 */
class MemberGroupRepository extends AbstractRepository
{
    // =========================================================================
    // LECTURE - Groupes
    // =========================================================================

    /**
     * Liste tous les groupes d'un tenant avec compteurs.
     */
    public function listForTenant(string $tenantId, bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE mg.tenant_id = :tid AND mg.is_active = true' : 'WHERE mg.tenant_id = :tid';

        return $this->selectAll(
            "SELECT
                mg.id,
                mg.tenant_id,
                mg.name,
                mg.description,
                mg.color,
                mg.sort_order,
                mg.is_active,
                mg.created_at,
                mg.updated_at,
                COUNT(mga.member_id) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL) AS member_count,
                COALESCE(SUM(COALESCE(m.voting_power, 1.0)) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL), 0) AS total_weight
            FROM member_groups mg
            LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
            LEFT JOIN members m ON m.id = mga.member_id
            {$where}
            GROUP BY mg.id
            ORDER BY mg.sort_order ASC, mg.name ASC",
            [':tid' => $tenantId]
        );
    }

    /**
     * Trouve un groupe par ID.
     */
    public function findById(string $groupId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT
                mg.*,
                COUNT(mga.member_id) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL) AS member_count,
                COALESCE(SUM(COALESCE(m.voting_power, 1.0)) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL), 0) AS total_weight
            FROM member_groups mg
            LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
            LEFT JOIN members m ON m.id = mga.member_id
            WHERE mg.id = :id AND mg.tenant_id = :tid
            GROUP BY mg.id",
            [':id' => $groupId, ':tid' => $tenantId]
        );
    }

    /**
     * Trouve un groupe par nom (pour unicite).
     */
    public function findByName(string $name, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM member_groups WHERE tenant_id = :tid AND LOWER(name) = LOWER(:name)",
            [':tid' => $tenantId, ':name' => $name]
        );
    }

    /**
     * Verifie si un nom de groupe existe deja (excluant un ID).
     */
    public function nameExists(string $name, string $tenantId, ?string $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM member_groups WHERE tenant_id = :tid AND LOWER(name) = LOWER(:name)";
        $params = [':tid' => $tenantId, ':name' => $name];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        return (bool) $this->scalar($sql, $params);
    }

    /**
     * Compte le nombre de groupes actifs d'un tenant.
     */
    public function countForTenant(string $tenantId): int
    {
        return (int) ($this->scalar(
            "SELECT COUNT(*) FROM member_groups WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    // =========================================================================
    // ECRITURE - Groupes
    // =========================================================================

    /**
     * Cree un nouveau groupe.
     */
    public function create(
        string $tenantId,
        string $name,
        ?string $description = null,
        ?string $color = null,
        ?int $sortOrder = null
    ): array {
        $id = $this->generateUuid();
        $color = $color ?: '#6366f1';
        $sortOrder = $sortOrder ?? 0;

        return $this->insertReturning(
            "INSERT INTO member_groups (id, tenant_id, name, description, color, sort_order)
             VALUES (:id, :tid, :name, :desc, :color, :sort)
             RETURNING *",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':name' => $name,
                ':desc' => $description,
                ':color' => $color,
                ':sort' => $sortOrder,
            ]
        );
    }

    /**
     * Met a jour un groupe.
     */
    public function update(
        string $groupId,
        string $tenantId,
        string $name,
        ?string $description = null,
        ?string $color = null,
        ?int $sortOrder = null,
        ?bool $isActive = null
    ): ?array {
        $sets = ['name = :name', 'description = :desc', 'updated_at = NOW()'];
        $params = [
            ':id' => $groupId,
            ':tid' => $tenantId,
            ':name' => $name,
            ':desc' => $description,
        ];

        if ($color !== null) {
            $sets[] = 'color = :color';
            $params[':color'] = $color;
        }

        if ($sortOrder !== null) {
            $sets[] = 'sort_order = :sort';
            $params[':sort'] = $sortOrder;
        }

        if ($isActive !== null) {
            $sets[] = 'is_active = :active';
            $params[':active'] = $isActive;
        }

        return $this->insertReturning(
            "UPDATE member_groups SET " . implode(', ', $sets) . "
             WHERE id = :id AND tenant_id = :tid
             RETURNING *",
            $params
        );
    }

    /**
     * Supprime un groupe (et ses assignations par cascade).
     */
    public function delete(string $groupId, string $tenantId): bool
    {
        return $this->execute(
            "DELETE FROM member_groups WHERE id = :id AND tenant_id = :tid",
            [':id' => $groupId, ':tid' => $tenantId]
        ) > 0;
    }

    /**
     * Desactive un groupe (soft delete).
     */
    public function deactivate(string $groupId, string $tenantId): bool
    {
        return $this->execute(
            "UPDATE member_groups SET is_active = false, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid",
            [':id' => $groupId, ':tid' => $tenantId]
        ) > 0;
    }

    // =========================================================================
    // LECTURE - Assignations
    // =========================================================================

    /**
     * Liste les membres d'un groupe.
     */
    public function listMembersInGroup(string $groupId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                m.id,
                m.full_name,
                m.email,
                COALESCE(m.voting_power, 1.0) AS voting_power,
                m.is_active,
                mga.assigned_at
            FROM member_group_assignments mga
            JOIN members m ON m.id = mga.member_id AND m.tenant_id = :tid
            WHERE mga.group_id = :gid AND m.deleted_at IS NULL
            ORDER BY m.full_name ASC",
            [':gid' => $groupId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste les groupes d'un membre.
     */
    public function listGroupsForMember(string $memberId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                mg.id,
                mg.name,
                mg.description,
                mg.color,
                mg.sort_order,
                mga.assigned_at
            FROM member_group_assignments mga
            JOIN member_groups mg ON mg.id = mga.group_id AND mg.tenant_id = :tid AND mg.is_active = true
            WHERE mga.member_id = :mid
            ORDER BY mg.sort_order ASC, mg.name ASC",
            [':mid' => $memberId, ':tid' => $tenantId]
        );
    }

    /**
     * Verifie si un membre appartient a un groupe.
     */
    public function isMemberInGroup(string $memberId, string $groupId): bool
    {
        return (bool) $this->scalar(
            "SELECT 1 FROM member_group_assignments WHERE member_id = :mid AND group_id = :gid",
            [':mid' => $memberId, ':gid' => $groupId]
        );
    }

    // =========================================================================
    // ECRITURE - Assignations
    // =========================================================================

    /**
     * Assigne un membre a un groupe.
     */
    public function assignMember(string $memberId, string $groupId, ?string $assignedBy = null): bool
    {
        // Upsert pour eviter les doublons
        return $this->execute(
            "INSERT INTO member_group_assignments (member_id, group_id, assigned_by)
             VALUES (:mid, :gid, :by)
             ON CONFLICT (member_id, group_id) DO UPDATE SET assigned_at = NOW(), assigned_by = EXCLUDED.assigned_by",
            [':mid' => $memberId, ':gid' => $groupId, ':by' => $assignedBy]
        ) >= 0; // INSERT returns 1, UPDATE returns 1
    }

    /**
     * Retire un membre d'un groupe.
     */
    public function unassignMember(string $memberId, string $groupId): bool
    {
        return $this->execute(
            "DELETE FROM member_group_assignments WHERE member_id = :mid AND group_id = :gid",
            [':mid' => $memberId, ':gid' => $groupId]
        ) > 0;
    }

    /**
     * Definit les groupes d'un membre (remplace tous les groupes existants).
     */
    public function setMemberGroups(string $memberId, array $groupIds, ?string $assignedBy = null): void
    {
        // Supprimer les assignations existantes
        $this->execute(
            "DELETE FROM member_group_assignments WHERE member_id = :mid",
            [':mid' => $memberId]
        );

        // Ajouter les nouvelles assignations
        if (!empty($groupIds)) {
            foreach ($groupIds as $groupId) {
                $this->assignMember($memberId, $groupId, $assignedBy);
            }
        }
    }

    /**
     * Assigne plusieurs membres a un groupe.
     */
    public function bulkAssignToGroup(string $groupId, array $memberIds, ?string $assignedBy = null): int
    {
        $count = 0;
        foreach ($memberIds as $memberId) {
            if ($this->assignMember($memberId, $groupId, $assignedBy)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Retire tous les membres d'un groupe.
     */
    public function clearGroup(string $groupId): int
    {
        return $this->execute(
            "DELETE FROM member_group_assignments WHERE group_id = :gid",
            [':gid' => $groupId]
        );
    }

    // =========================================================================
    // STATISTIQUES
    // =========================================================================

    /**
     * Statistiques par groupe pour un tenant.
     */
    public function getGroupStats(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                mg.id,
                mg.name,
                mg.color,
                COUNT(mga.member_id) FILTER (WHERE m.is_active = true) AS active_members,
                COUNT(mga.member_id) FILTER (WHERE m.is_active = false) AS inactive_members,
                COALESCE(SUM(COALESCE(m.voting_power, 1.0)) FILTER (WHERE m.is_active = true), 0) AS total_active_weight
            FROM member_groups mg
            LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
            LEFT JOIN members m ON m.id = mga.member_id AND m.deleted_at IS NULL
            WHERE mg.tenant_id = :tid AND mg.is_active = true
            GROUP BY mg.id
            ORDER BY mg.sort_order ASC, mg.name ASC",
            [':tid' => $tenantId]
        );
    }

    /**
     * Compte les membres sans groupe.
     */
    public function countMembersWithoutGroup(string $tenantId): int
    {
        return (int) ($this->scalar(
            "SELECT COUNT(*)
            FROM members m
            WHERE m.tenant_id = :tid
              AND m.is_active = true
              AND m.deleted_at IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM member_group_assignments mga
                JOIN member_groups mg ON mg.id = mga.group_id AND mg.is_active = true
                WHERE mga.member_id = m.id
              )",
            [':tid' => $tenantId]
        ) ?? 0);
    }
}
