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
        ?float $thresholdCall2 = null
    ): void {
        $this->execute(
            "INSERT INTO quorum_policies (id, tenant_id, name, mode, denominator, threshold,
             threshold2, denominator2, include_proxies, count_remote, threshold_call2, created_at)
             VALUES (:id, :tid, :name, :mode, :den, :thr, :thr2, :den2, :ip, :cr, :tc2, now())",
            [
                ':id' => $id, ':tid' => $tenantId, ':name' => $name,
                ':mode' => $mode, ':den' => $denominator, ':thr' => $threshold,
                ':thr2' => $threshold2, ':den2' => $denominator2,
                ':ip' => $includeProxies ? 't' : 'f',
                ':cr' => $countRemote ? 't' : 'f',
                ':tc2' => $thresholdCall2,
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

    public function deleteVotePolicy(string $id, string $tenantId): int
    {
        return $this->execute(
            "DELETE FROM vote_policies WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }
}
