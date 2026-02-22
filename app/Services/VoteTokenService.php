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
 *  1. generate() creates a random token, stores its HMAC-SHA256 hash in DB.
 *  2. The raw token is sent to the voter (QR, link, etc.).
 *  3. validateAndConsume() atomically verifies + consumes the token.
 *
 * IMPORTANT: Token hashes use hash_hmac('sha256', $token, APP_SECRET) —
 * this MUST match VoteTokenController::generate() and VotePublicController::vote().
 * Never use plain hash('sha256') for vote tokens.
 */
final class VoteTokenService {
    private const TOKEN_BYTES = 32;
    private const DEFAULT_TTL_SECONDS = 3600; // 1h

    private MeetingRepository $meetingRepo;
    private VoteTokenRepository $tokenRepo;

    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?VoteTokenRepository $tokenRepo = null,
    ) {
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
        $this->tokenRepo = $tokenRepo ?? new VoteTokenRepository();
    }

    /**
     * Computes the HMAC-SHA256 hash of a raw token.
     * Must match VoteTokenController and VotePublicController.
     */
    private static function hashToken(string $rawToken): string {
        return hash_hmac('sha256', $rawToken, APP_SECRET);
    }

    /**
     * Generates a vote token and persists its hash.
     *
     * @return array{token: string, token_hash: string, expires_at: string}
     */
    public function generate(
        string $meetingId,
        string $memberId,
        string $motionId,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        string $tenantId,
    ): array {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        $motionId = trim($motionId);

        if ($meetingId === '' || $memberId === '' || $motionId === '') {
            throw new InvalidArgumentException('meeting_id, member_id et motion_id sont obligatoires');
        }
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }

        // Generate raw token (hex) and its HMAC-SHA256 hash (keyed with APP_SECRET)
        $tokenRaw = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = self::hashToken($tokenRaw);

        $ttlSeconds = max(60, $ttlSeconds);
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds);

        $this->tokenRepo->insert($tokenHash, $tenantId, $meetingId, $memberId, $motionId, $expiresAt);

        return [
            'token' => $tokenRaw,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validates a token: exists, not expired, not used.
     *
     * @deprecated Use validateAndConsume() for atomic validate+consume (no TOCTOU race).
     *
     * @return array{valid: bool, token_hash: string, meeting_id?: string, member_id?: string, motion_id?: string, reason?: string}
     */
    public function validate(string $token): array {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'token_hash' => '', 'reason' => 'token_empty'];
        }

        $tokenHash = self::hashToken($token);

        $row = $this->tokenRepo->findByHash($tokenHash);

        if (!$row) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_not_found'];
        }

        if ($row['used_at'] !== null) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_already_used'];
        }

        $expiresAt = strtotime((string) $row['expires_at']);
        if ($expiresAt !== false && $expiresAt < time()) {
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => 'token_expired'];
        }

        return [
            'valid' => true,
            'token_hash' => $tokenHash,
            'meeting_id' => (string) $row['meeting_id'],
            'member_id' => (string) $row['member_id'],
            'motion_id' => (string) $row['motion_id'],
        ];
    }

    /**
     * Marks a token as used (consumed).
     *
     * @deprecated Use validateAndConsume() for atomic validate+consume (no TOCTOU race).
     */
    public function consume(string $token, string $tenantId = ''): bool {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $tokenHash = self::hashToken($token);

        if ($tenantId === '') {
            $row = $this->tokenRepo->findByHash($tokenHash);
            if (!$row) {
                return false;
            }
            $tenantId = (string) $row['tenant_id'];
        }

        $affected = $this->tokenRepo->consume($tokenHash, $tenantId);

        return $affected > 0;
    }

    /**
     * Atomic validate-and-consume: validates the token and marks it used in a single
     * UPDATE query, eliminating the TOCTOU race between validate() and consume().
     *
     * @return array{valid: bool, token_hash: string, meeting_id?: string, member_id?: string, motion_id?: string, reason?: string}
     */
    public function validateAndConsume(string $token): array {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'token_hash' => '', 'reason' => 'token_empty'];
        }

        $tokenHash = self::hashToken($token);

        $row = $this->tokenRepo->consumeIfValid($tokenHash);

        if (!$row) {
            // Single diagnostic query to determine failure reason
            $reason = $this->tokenRepo->diagnoseFailure($tokenHash);
            return ['valid' => false, 'token_hash' => $tokenHash, 'reason' => $reason];
        }

        return [
            'valid' => true,
            'token_hash' => $tokenHash,
            'meeting_id' => (string) $row['meeting_id'],
            'member_id' => (string) $row['member_id'],
            'motion_id' => (string) $row['motion_id'],
        ];
    }

    /**
     * Revokes all unused tokens for a given motion.
     */
    public function revokeForMotion(string $motionId, string $tenantId): int {
        return $this->tokenRepo->revokeForMotion($motionId, $tenantId);
    }
}
