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
     * Trouve un token valide (non utilise, non expire) par son hash.
     */
    public function findValidByHash(string $tokenHash): ?array
    {
        return $this->selectOne(
            "SELECT token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at, used_at
             FROM vote_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()",
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
     * Atomic validate-and-consume: marks a valid token as used and returns its data,
     * or returns null if token is not found, already used, or expired.
     * Single UPDATE…RETURNING eliminates TOCTOU race condition.
     */
    public function consumeIfValid(string $tokenHash): ?array
    {
        return $this->selectOne(
            "UPDATE vote_tokens
             SET used_at = now()
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             RETURNING token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at, used_at",
            [':hash' => $tokenHash]
        );
    }

    /**
     * Compte tous les tokens de vote (global).
     */
    public function countAll(): ?int
    {
        try {
            return (int)($this->scalar("SELECT COUNT(*) FROM vote_tokens") ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
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

    /**
     * Trouve un token actif non utilise pour un membre et une motion.
     */
    public function findActiveForMember(string $tenantId, string $meetingId, string $motionId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT token_hash FROM vote_tokens
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo AND member_id = :mb
               AND used_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId, ':mb' => $memberId]
        );
    }

    /**
     * Insere un token avec expiration calculee en minutes depuis maintenant.
     */
    public function insertWithExpiry(
        string $hash,
        string $tenantId,
        string $meetingId,
        string $memberId,
        string $motionId,
        int $expiresMinutes
    ): int {
        return $this->execute(
            "INSERT INTO vote_tokens(token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at, used_at, created_at)
             VALUES(:h, :tid, :mid, :mb, :mo, now() + make_interval(mins => :mins), NULL, now())",
            [
                ':h' => $hash, ':tid' => $tenantId, ':mid' => $meetingId,
                ':mb' => $memberId, ':mo' => $motionId, ':mins' => $expiresMinutes,
            ]
        );
    }

    /**
     * Compte les tokens actifs non utilises pour une motion.
     */
    public function countActiveUnused(string $tenantId, string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM vote_tokens
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo
               AND used_at IS NULL AND expires_at > NOW()",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les tokens expires non utilises pour une motion.
     */
    public function countExpiredUnused(string $tenantId, string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM vote_tokens
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo
               AND used_at IS NULL AND expires_at <= NOW()",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les tokens utilises pour une motion.
     */
    public function countUsed(string $tenantId, string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM vote_tokens
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo
               AND used_at IS NOT NULL",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    /**
     * Supprime les tokens non utilises pour un membre + motion (idempotence).
     */
    public function deleteUnusedByMotionAndMember(string $meetingId, string $motionId, string $memberId): void
    {
        $this->execute(
            "DELETE FROM vote_tokens
             WHERE meeting_id = :mid AND motion_id = :mo AND member_id = :mb AND used_at IS NULL",
            [':mid' => $meetingId, ':mo' => $motionId, ':mb' => $memberId]
        );
    }

    /**
     * Supprime tous les tokens lies aux motions d'une seance (USING JOIN).
     */
    public function deleteByMeetingMotions(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "DELETE FROM vote_tokens vt
             USING motions mo
             WHERE vt.motion_id = mo.id
               AND mo.meeting_id = :mid
               AND mo.tenant_id = :tid",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }
}
