<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\VoteTokenRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * VoteTokenService - Vote token management (vote_tokens table).
 *
 * Flow:
 *  1. generate() creates a random token, stores its SHA-256 hash in DB.
 *  2. The raw token is sent to the voter (QR, link, etc.).
 *  3. validate() verifies the hash, expiration and non-usage.
 *  4. consume() marks the token as used.
 */
final class VoteTokenService
{
    private const TOKEN_BYTES = 32;
    private const DEFAULT_TTL_SECONDS = 3600; // 1h

    /**
     * Generates a vote token and persists its hash.
     *
     * @return array{token: string, token_hash: string, expires_at: string}
     */
    public static function generate(
        string $meetingId,
        string $memberId,
        string $motionId,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        ?string $tenantId = null
    ): array {
        $meetingId = trim($meetingId);
        $memberId  = trim($memberId);
        $motionId  = trim($motionId);

        if ($meetingId === '' || $memberId === '' || $motionId === '') {
            throw new InvalidArgumentException('meeting_id, member_id et motion_id sont obligatoires');
        }

        // Resolve tenant
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? api_current_tenant_id());
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }

        // Generate raw token (hex) and its SHA-256 hash
        $tokenRaw  = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $tokenRaw);

        $ttlSeconds = max(60, $ttlSeconds);
        $expiresAt  = gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds);

        $tokenRepo = new VoteTokenRepository();
        $tokenRepo->insert($tokenHash, $tenantId, $meetingId, $memberId, $motionId, $expiresAt);

        return [
            'token'      => $tokenRaw,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validates a token: exists, not expired, not used.
     *
     * @return array{valid: bool, token_hash: string, meeting_id?: string, member_id?: string, motion_id?: string, reason?: string}
     */
    public static function validate(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'token_hash' => '', 'reason' => 'token_empty'];
        }

        $tokenHash = hash('sha256', $token);

        $tokenRepo = new VoteTokenRepository();
        $row = $tokenRepo->findByHash($tokenHash);

        if (!$row) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_not_found'];
        }

        if ($row['used_at'] !== null) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_already_used'];
        }

        $expiresAt = strtotime((string)$row['expires_at']);
        if ($expiresAt !== false && $expiresAt < time()) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_expired'];
        }

        return [
            'valid'      => true,
            'token_hash' => $tokenHash,
            'meeting_id' => (string)$row['meeting_id'],
            'member_id'  => (string)$row['member_id'],
            'motion_id'  => (string)$row['motion_id'],
        ];
    }

    /**
     * Marks a token as used (consumed).
     */
    public static function consume(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $tokenRepo = new VoteTokenRepository();
        $affected = $tokenRepo->consume($tokenHash);

        return $affected > 0;
    }

    /**
     * Atomic validate-and-consume: validates the token and marks it used in a single
     * UPDATE query, eliminating the TOCTOU race between validate() and consume().
     *
     * @return array{valid: bool, token_hash: string, meeting_id?: string, member_id?: string, motion_id?: string, reason?: string}
     */
    public static function validateAndConsume(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'token_hash' => '', 'reason' => 'token_empty'];
        }

        $tokenHash = hash('sha256', $token);

        $tokenRepo = new VoteTokenRepository();
        $row = $tokenRepo->consumeIfValid($tokenHash);

        if (!$row) {
            // Token not found, already used, or expired — check which
            $existing = $tokenRepo->findByHash($tokenHash);
            if (!$existing) {
                return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_not_found'];
            }
            if ($existing['used_at'] !== null) {
                return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_already_used'];
            }
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_expired'];
        }

        return [
            'valid'      => true,
            'token_hash' => $tokenHash,
            'meeting_id' => (string)$row['meeting_id'],
            'member_id'  => (string)$row['member_id'],
            'motion_id'  => (string)$row['motion_id'],
        ];
    }

    /**
     * Revokes all unused tokens for a given motion.
     */
    public static function revokeForMotion(string $motionId, string $tenantId = ''): int
    {
        $tokenRepo = new VoteTokenRepository();
        return $tokenRepo->revokeForMotion($motionId, $tenantId);
    }
}
