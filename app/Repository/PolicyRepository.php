<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les politiques de vote et de quorum.
 */
class PolicyRepository extends AbstractRepository
{
    // =========================================================================
    // QUORUM POLICIES
    // =========================================================================

    public function findQuorumPolicy(string $id): ?array
    {
        return $this->selectOne(
            "SELECT * FROM quorum_policies WHERE id = :id",
            [':id' => $id]
        );
    }

    public function findQuorumPolicyForTenant(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM quorum_policies WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }

    public function listQuorumPolicies(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT * FROM quorum_policies WHERE tenant_id = :tid ORDER BY name",
            [':tid' => $tenantId]
        );
    }

    public function createQuorumPolicy(
        string $id,
        string $tenantId,
        string $name,
        string $mode,
        string $denominator,
        float $threshold,
        ?float $threshold2 = null,
        ?string $denominator2 = null,
        bool $includeProxies = true,
        bool $countRemote = true,
        ?float $thresholdCall2 = null,
        ?string $description = null
    ): void {
        $this->execute(
            "INSERT INTO quorum_policies (id, tenant_id, name, description, mode, denominator, threshold,
             threshold_call2, denominator2, threshold2, include_proxies, count_remote, created_at, updated_at)
             VALUES (:id, :tid, :name, :desc, :mode, :den, :thr, :tc2, :den2, :thr2, :ip, :cr, NOW(), NOW())",
            [
                ':id' => $id, ':tid' => $tenantId, ':name' => $name, ':desc' => $description,
                ':mode' => $mode, ':den' => $denominator, ':thr' => $threshold,
                ':tc2' => $thresholdCall2, ':den2' => $denominator2, ':thr2' => $threshold2,
                ':ip' => $includeProxies ? 't' : 'f',
                ':cr' => $countRemote ? 't' : 'f',
            ]
        );
    }

    public function updateQuorumPolicy(
        string $id,
        string $tenantId,
        string $name,
        ?string $description,
        string $mode,
        string $denominator,
        float $threshold,
        ?float $thresholdCall2 = null,
        ?string $denominator2 = null,
        ?float $threshold2 = null,
        bool $includeProxies = true,
        bool $countRemote = true
    ): void {
        $this->execute(
            "UPDATE quorum_policies SET
                name=:name, description=:desc, mode=:mode, denominator=:den, threshold=:thr,
                threshold_call2=:c2, denominator2=:den2, threshold2=:thr2,
                include_proxies=:ip, count_remote=:cr, updated_at=NOW()
             WHERE tenant_id=:tid AND id=:id",
            [
                ':name' => $name, ':desc' => $description, ':mode' => $mode,
                ':den' => $denominator, ':thr' => $threshold,
                ':c2' => $thresholdCall2, ':den2' => $denominator2, ':thr2' => $threshold2,
                ':ip' => $includeProxies ? 't' : 'f',
                ':cr' => $countRemote ? 't' : 'f',
                ':tid' => $tenantId, ':id' => $id,
            ]
        );
    }

    public function deleteQuorumPolicy(string $id, string $tenantId): int
    {
        return $this->execute(
            "DELETE FROM quorum_policies WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }

    /**
     * Retourne le nom d'une politique de quorum par ID et tenant.
     */
    public function findQuorumPolicyName(string $tenantId, string $id): ?string
    {
        $val = $this->scalar(
            "SELECT name FROM quorum_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $id]
        );
        return $val !== false && $val !== null ? (string)$val : null;
    }

    // =========================================================================
    // VOTE POLICIES
    // =========================================================================

    public function findVotePolicy(string $id): ?array
    {
        return $this->selectOne(
            "SELECT * FROM vote_policies WHERE id = :id",
            [':id' => $id]
        );
    }

    public function findVotePolicyForTenant(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM vote_policies WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }

    public function listVotePolicies(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT * FROM vote_policies WHERE tenant_id = :tid ORDER BY name",
            [':tid' => $tenantId]
        );
    }

    public function createVotePolicy(
        string $id,
        string $tenantId,
        string $name,
        string $base,
        float $threshold,
        bool $abstentionAsAgainst = false
    ): void {
        $this->execute(
            "INSERT INTO vote_policies (id, tenant_id, name, base, threshold, abstention_as_against, created_at)
             VALUES (:id, :tid, :name, :base, :thr, :aaa, now())",
            [
                ':id' => $id, ':tid' => $tenantId, ':name' => $name,
                ':base' => $base, ':thr' => $threshold,
                ':aaa' => $abstentionAsAgainst ? 't' : 'f',
            ]
        );
    }

    public function updateVotePolicy(
        string $id,
        string $tenantId,
        string $name,
        ?string $description,
        string $base,
        float $threshold,
        bool $abstentionAsAgainst = false
    ): void {
        $this->execute(
            "UPDATE vote_policies SET
                name=:name, description=:desc, base=:base, threshold=:thr,
                abstention_as_against=:aaa, updated_at=NOW()
             WHERE tenant_id=:tid AND id=:id",
            [
                ':name' => $name, ':desc' => $description,
                ':base' => $base, ':thr' => $threshold,
                ':aaa' => $abstentionAsAgainst ? 't' : 'f',
                ':tid' => $tenantId, ':id' => $id,
            ]
        );
    }

    /**
     * Retourne le nom d'une politique de vote par ID et tenant.
     */
    public function findVotePolicyName(string $tenantId, string $id): ?string
    {
        $val = $this->scalar(
            "SELECT name FROM vote_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $id]
        );
        return $val !== false && $val !== null ? (string)$val : null;
    }

    public function deleteVotePolicy(string $id, string $tenantId): int
    {
        return $this->execute(
            "DELETE FROM vote_policies WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }
}
