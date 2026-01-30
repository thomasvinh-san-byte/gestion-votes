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
