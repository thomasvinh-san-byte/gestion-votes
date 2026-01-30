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
