<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\VoteTokenRepository;

/**
 * VoteTokenService — Gestion des tokens de vote (vote_tokens table).
 *
 * Flux:
 *  1. generate() crée un token aléatoire, stocke son hash SHA-256 en BDD.
 *  2. Le token brut est transmis au votant (QR, lien, etc.).
 *  3. validate() vérifie le hash, l'expiration et le non-usage.
 *  4. consume() marque le token comme utilisé.
 */
final class VoteTokenService
{
    private const TOKEN_BYTES = 32;
    private const DEFAULT_TTL_SECONDS = 3600; // 1h

    /**
     * Génère un token de vote et persiste son hash.
     *
     * @return array{token: string, token_hash: string, expires_at: string}
     */
    public static function generate(
        string $meetingId,
        string $memberId,
        string $motionId,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): array {
        $meetingId = trim($meetingId);
        $memberId  = trim($memberId);
        $motionId  = trim($motionId);

        if ($meetingId === '' || $memberId === '' || $motionId === '') {
            throw new InvalidArgumentException('meeting_id, member_id et motion_id sont obligatoires');
        }

        // Résoudre le tenant
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findById($meetingId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        $tenantId = (string)$meeting['tenant_id'];

        // Générer le token brut (hex) et son hash SHA-256
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
     * Valide un token : existe, non expiré, non utilisé.
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
     * Marque un token comme utilisé (consommé).
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
     * Révoque tous les tokens non utilisés pour une motion donnée.
     */
    public static function revokeForMotion(string $motionId): int
    {
        $tokenRepo = new VoteTokenRepository();
        return $tokenRepo->revokeForMotion($motionId);
    }
}
