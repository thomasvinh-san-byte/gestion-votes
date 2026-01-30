<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les tokens de vote (vote_tokens).
 */
class VoteTokenRepository extends AbstractRepository
{
    /**
     * Insere un token de vote (ON CONFLICT DO NOTHING).
     */
    public function insert(
        string $tokenHash,
        string $tenantId,
        string $meetingId,
        string $memberId,
        string $motionId,
        string $expiresAt
    ): void {
        $this->execute(
            "INSERT INTO vote_tokens (token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at)
             VALUES (:hash, :tid, :mid, :mem, :mot, :exp)
             ON CONFLICT (token_hash) DO NOTHING",
            [
                ':hash' => $tokenHash, ':tid' => $tenantId,
                ':mid' => $meetingId, ':mem' => $memberId,
                ':mot' => $motionId, ':exp' => $expiresAt,
            ]
        );
    }

    /**
     * Trouve un token par son hash.
     */
    public function findByHash(string $tokenHash): ?array
    {
        return $this->selectOne(
            "SELECT token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at, used_at
             FROM vote_tokens
             WHERE token_hash = :hash",
            [':hash' => $tokenHash]
        );
    }

    /**
     * Marque un token comme consomme (used_at = now()).
     */
    public function consume(string $tokenHash): int
    {
        return $this->execute(
            "UPDATE vote_tokens SET used_at = now() WHERE token_hash = :hash AND used_at IS NULL",
            [':hash' => $tokenHash]
        );
    }

    /**
     * Revoque tous les tokens non utilises pour une motion.
     */
    public function revokeForMotion(string $motionId): int
    {
        return $this->execute(
            "UPDATE vote_tokens SET used_at = now() WHERE motion_id = :mid AND used_at IS NULL",
            [':mid' => $motionId]
        );
    }
}
