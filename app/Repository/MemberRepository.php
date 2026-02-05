<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for members.
 */
class MemberRepository extends AbstractRepository
{
    /**
     * Lists all members (not deleted) for a tenant.
     */
    public function listByTenant(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, tenant_id, external_ref, full_name, email, vote_weight,
                    voting_power, role, is_active, created_at, updated_at
             FROM members
             WHERE tenant_id = :tenant_id AND deleted_at IS NULL
             ORDER BY full_name",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Lists active members for a tenant (with voting power).
     */
    public function listActive(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, full_name, email, role,
                    COALESCE(voting_power, vote_weight, 1.0) AS voting_power,
                    vote_weight, is_active, created_at, updated_at, tenant_id
             FROM members
             WHERE tenant_id = :tenant_id
               AND is_active = true
               AND deleted_at IS NULL
             ORDER BY full_name ASC",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Find a member by ID for a specific tenant.
     */
    public function findByIdForTenant(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM members WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Check if a member exists for a tenant.
     */
    public function existsForTenant(string $id, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM members WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    /**
     * @deprecated Use findByIdForTenant() instead for tenant isolation.
     */
    public function findById(string $id): ?array
    {
        return $this->selectOne(
            "SELECT * FROM members WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Creates a member and returns its ID.
     */
    /**
     * Count of active members for a tenant.
     */
    public function countActive(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Count of active and not deleted members for a tenant.
     */
    public function countActiveNotDeleted(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Total weight of active members for a tenant.
     */
    public function sumActiveWeight(string $tenantId): float
    {
        return (float)($this->scalar(
            "SELECT COALESCE(SUM(COALESCE(voting_power, vote_weight, 1.0)), 0)
             FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0.0);
    }

    /**
     * Lists IDs of active members for a tenant.
     */
    public function listActiveIds(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        );
    }

    /**
     * Checks if a member exists for a tenant.
     */
    public function existsForTenantById(string $memberId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM members WHERE id = :id AND tenant_id = :tid",
            [':id' => $memberId, ':tid' => $tenantId]
        );
    }

    /**
     * Count of non-deleted members for a tenant (for dashboard eligible count).
     */
    public function countNotDeleted(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Sum of vote_weight for non-deleted members (for dashboard eligible weight).
     */
    public function sumNotDeletedVoteWeight(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COALESCE(SUM(vote_weight), 0) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Lists active members with email (for invitations).
     */
    public function listActiveWithEmail(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, full_name, email
             FROM members
             WHERE tenant_id = :tid AND is_active = true
               AND email IS NOT NULL AND email <> ''
             ORDER BY full_name ASC",
            [':tid' => $tenantId]
        );
    }

    /**
     * Finds an active member with voting weight (for manual_vote).
     */
    public function findActiveWithWeight(string $tenantId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT id, vote_weight FROM members
             WHERE tenant_id = :tid AND id = :id AND is_active = true",
            [':tid' => $tenantId, ':id' => $memberId]
        );
    }

    /**
     * Lists active members for president selection.
     */
    public function listActiveForPresident(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, full_name, email, role
             FROM members
             WHERE tenant_id = :tid AND is_active = true
             ORDER BY full_name ASC",
            [':tid' => $tenantId]
        );
    }

    /**
     * Insere un membre de test (seed) avec ON CONFLICT DO NOTHING.
     * Retourne true si la ligne a ete inseree, false sinon.
     */
    public function insertSeedMember(string $id, string $tenantId, string $fullName): bool
    {
        $rows = $this->execute(
            "INSERT INTO members (id, tenant_id, full_name, is_active, vote_weight, created_at, updated_at)
             VALUES (:id, :tid, :name, true, 1, now(), now())
             ON CONFLICT DO NOTHING",
            [':id' => $id, ':tid' => $tenantId, ':name' => $fullName]
        );
        return $rows > 0;
    }

    /**
     * Export CSV: membres avec presences et procurations pour une seance.
     */
    public function listExportForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                m.id AS member_id,
                m.full_name,
                (CASE WHEN m.is_active THEN '1' ELSE '0' END) AS is_active,
                COALESCE(m.voting_power, 0) AS voting_power,
                COALESCE(a.mode::text, 'absent') AS attendance_mode,
                a.checked_in_at,
                a.checked_out_at,
                pr.receiver_member_id AS proxy_to_member_id,
                r.full_name AS proxy_to_name
             FROM members m
             LEFT JOIN attendances a
                    ON a.meeting_id = ? AND a.member_id = m.id
             LEFT JOIN proxies pr
                    ON pr.meeting_id = ? AND pr.giver_member_id = m.id
             LEFT JOIN members r
                    ON r.id = pr.receiver_member_id
             WHERE m.tenant_id = ?
             ORDER BY m.full_name ASC",
            [$meetingId, $meetingId, $tenantId]
        );
    }

    public function create(
        string $id,
        string $tenantId,
        string $fullName,
        ?string $email,
        float $votingPower,
        bool $isActive
    ): void {
        $this->execute(
            "INSERT INTO members (id, tenant_id, full_name, email, vote_weight, voting_power, is_active, created_at, updated_at)
             VALUES (:id, :tid, :name, :email, :vw, :vp, :active, now(), now())",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':name' => $fullName,
                ':email' => $email !== '' ? $email : null,
                ':vw' => $votingPower,
                ':vp' => $votingPower,
                ':active' => $isActive ? 'true' : 'false',
            ]
        );
    }

    /**
     * Liste tous les membres actifs avec leur mode de presence pour une seance.
     */
    public function listWithAttendanceForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name,
                    COALESCE(m.voting_power, m.vote_weight, 1.0) AS voting_power,
                    a.mode AS attendance_mode
             FROM members m
             LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid
             WHERE m.tenant_id = :tid AND m.is_active = true
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste les membres d'une seance (fallback sans attendances).
     */
    public function listByMeetingFallback(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id AS member_id FROM members
             WHERE tenant_id = :tid AND meeting_id = :mid",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les membres eligibles (avec attendance present/remote/proxy) pour une seance.
     */
    public function listEligibleForMeeting(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name
             FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = m.tenant_id
             WHERE m.tenant_id = :tid AND m.is_active = true AND a.mode IN ('present','remote','proxy')
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste les membres actifs d'une seance (fallback avec meeting_id sur members).
     */
    public function listActiveFallbackByMeeting(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id AS member_id, full_name
             FROM members
             WHERE tenant_id = :tid AND meeting_id = :mid AND is_active = true
             ORDER BY full_name ASC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Trouve un membre par email (case-insensitive).
     */
    public function findByEmail(string $tenantId, string $email): ?array
    {
        return $this->selectOne(
            "SELECT id FROM members WHERE tenant_id = :tid AND LOWER(email) = :email",
            [':tid' => $tenantId, ':email' => strtolower($email)]
        );
    }

    /**
     * Trouve un membre par nom complet (case-insensitive).
     */
    public function findByFullName(string $tenantId, string $fullName): ?array
    {
        return $this->selectOne(
            "SELECT id FROM members WHERE tenant_id = :tid AND LOWER(full_name) = LOWER(:name)",
            [':tid' => $tenantId, ':name' => $fullName]
        );
    }

    /**
     * Met a jour un membre lors d'un import CSV.
     */
    public function updateImport(string $id, string $fullName, ?string $email, float $votingPower, bool $isActive): void
    {
        $this->execute(
            "UPDATE members SET
                full_name = :name, email = COALESCE(:email, email), voting_power = :vp, is_active = :active, updated_at = NOW()
             WHERE id = :id",
            [
                ':name' => $fullName, ':email' => $email, ':vp' => $votingPower,
                ':active' => $isActive ? 'true' : 'false', ':id' => $id,
            ]
        );
    }

    /**
     * Cree un membre avec UUID genere cote serveur (import CSV).
     * Retourne l'ID du membre cree.
     */
    public function createImport(string $tenantId, string $fullName, ?string $email, float $votingPower, bool $isActive): string
    {
        $result = $this->insertReturning(
            "INSERT INTO members (id, tenant_id, full_name, email, voting_power, is_active, created_at, updated_at)
             VALUES (gen_random_uuid(), :tid, :name, :email, :vp, :active, NOW(), NOW())
             RETURNING id",
            [
                ':tid' => $tenantId, ':name' => $fullName, ':email' => $email,
                ':vp' => $votingPower, ':active' => $isActive ? 'true' : 'false',
            ]
        );
        return $result['id'];
    }

    /**
     * Verifie si un membre a ete cree par un utilisateur.
     */
    public function isOwnedByUser(string $memberId, string $userId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM members WHERE id = :id AND created_by_user_id = :uid",
            [':id' => $memberId, ':uid' => $userId]
        );
    }

    /**
     * Soft delete un membre (marque deleted_at).
     */
    public function softDelete(string $id): void
    {
        $this->execute(
            "UPDATE members SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Lists all members of a tenant (active and inactive, not deleted).
     */
    public function listAll(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, full_name, full_name AS name, email, role,
                    COALESCE(voting_power, vote_weight, 1.0) AS voting_power,
                    vote_weight, is_active, created_at, updated_at, tenant_id
             FROM members
             WHERE tenant_id = :tenant_id
               AND deleted_at IS NULL
             ORDER BY full_name ASC",
            [':tenant_id' => $tenantId]
        );
    }
}
