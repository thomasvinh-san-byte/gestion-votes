<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository for password_resets table.
 *
 * Stores short-lived HMAC-SHA256 token hashes used for password reset flows.
 * Tokens expire after 1 hour and become single-use once markUsed() is called.
 */
class PasswordResetRepository extends AbstractRepository {
    /**
     * Inserts a new password reset token.
     */
    public function insert(string $tokenHash, string $tenantId, string $userId, string $expiresAt): void {
        $this->execute(
            'INSERT INTO password_resets (tenant_id, user_id, token_hash, expires_at)
             VALUES (:tid, :uid, :hash, :exp)',
            [
                ':tid'  => $tenantId,
                ':uid'  => $userId,
                ':hash' => $tokenHash,
                ':exp'  => $expiresAt,
            ],
        );
    }

    /**
     * Finds a valid (unused, non-expired) reset token by its hash.
     *
     * Returns the row or null if not found / already used / expired.
     */
    public function findByHash(string $tokenHash): ?array {
        return $this->selectOne(
            'SELECT id, tenant_id, user_id, token_hash, expires_at, used_at, created_at
             FROM password_resets
             WHERE token_hash = :hash
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1',
            [':hash' => $tokenHash],
        );
    }

    /**
     * Marks a token as used. Returns the number of rows affected (0 or 1).
     */
    public function markUsed(string $tokenHash): int {
        return $this->execute(
            'UPDATE password_resets
             SET used_at = NOW()
             WHERE token_hash = :hash
               AND used_at IS NULL',
            [':hash' => $tokenHash],
        );
    }

    /**
     * Deletes all tokens for a given user (call after successful password reset
     * to invalidate any other outstanding tokens for that user).
     */
    public function deleteForUser(string $userId): int {
        return $this->execute(
            'DELETE FROM password_resets WHERE user_id = :uid',
            [':uid' => $userId],
        );
    }

    /**
     * Deletes expired tokens that are older than 24 hours (cleanup job).
     */
    public function deleteExpired(): int {
        return $this->execute(
            "DELETE FROM password_resets
             WHERE expires_at < NOW() - INTERVAL '24 hours'",
        );
    }
}
