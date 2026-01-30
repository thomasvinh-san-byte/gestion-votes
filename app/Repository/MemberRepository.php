<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les membres.
 */
class MemberRepository extends AbstractRepository
{
    /**
     * Liste tous les membres (non supprimes) d'un tenant.
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
     * Liste les membres actifs d'un tenant (avec pouvoir de vote).
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
     * Trouve un membre par son ID.
     */
    public function findById(string $id): ?array
    {
        return $this->selectOne(
            "SELECT * FROM members WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Cree un membre et retourne son ID.
     */
    /**
     * Nombre de membres actifs d'un tenant.
     */
    public function countActive(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Poids total des membres actifs d'un tenant.
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
     * Liste les IDs des membres actifs d'un tenant.
     */
    public function listActiveIds(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        );
    }

    /**
     * Verifie qu'un membre existe pour un tenant.
     */
    public function existsForTenant(string $memberId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM members WHERE id = :id AND tenant_id = :tid",
            [':id' => $memberId, ':tid' => $tenantId]
        );
    }

    /**
     * Nombre de membres non supprimes d'un tenant (pour dashboard eligible count).
     */
    public function countNotDeleted(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Somme vote_weight des membres non supprimes (pour dashboard eligible weight).
     */
    public function sumNotDeletedVoteWeight(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COALESCE(SUM(vote_weight), 0) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Liste les membres actifs avec email (pour invitations).
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
     * Trouve un membre actif avec son poids de vote (pour manual_vote).
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
     * Liste les membres actifs pour selection president.
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
}
