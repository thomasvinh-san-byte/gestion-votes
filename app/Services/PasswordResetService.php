<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\PasswordResetRepository;
use AgVote\Repository\UserRepository;

/**
 * PasswordResetService — Secure password reset flow.
 *
 * Flow:
 *  1. requestReset($email): silently generates a token and queues a reset email.
 *     Unknown / inactive emails are ignored (no user enumeration).
 *  2. validateToken($rawToken): returns the token row if valid, null otherwise.
 *  3. resetPassword($rawToken, $newPassword): updates the user's password,
 *     invalidates the token, and removes all other tokens for that user.
 *
 * Token security:
 *  - Raw token: bin2hex(random_bytes(32)) — 64-hex-char string, 256 bits entropy
 *  - Stored hash: hash_hmac('sha256', $rawToken, APP_SECRET)
 *  - TTL: 1 hour
 *  - Single-use: markUsed() is called atomically on consume
 */
final class PasswordResetService {
    private const TOKEN_BYTES  = 32;
    private const TTL_SECONDS  = 3600; // 1 hour

    private PasswordResetRepository $resetRepo;
    private UserRepository $userRepo;
    private EmailQueueRepository $emailQueueRepo;

    public function __construct(
        ?PasswordResetRepository $resetRepo = null,
        ?UserRepository $userRepo = null,
        ?EmailQueueRepository $emailQueueRepo = null,
    ) {
        $this->resetRepo      = $resetRepo      ?? RepositoryFactory::getInstance()->passwordReset();
        $this->userRepo       = $userRepo       ?? RepositoryFactory::getInstance()->user();
        $this->emailQueueRepo = $emailQueueRepo ?? RepositoryFactory::getInstance()->emailQueue();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Initiates a password reset for the given email.
     *
     * Silently does nothing if the email is unknown or the user is inactive
     * (prevents user enumeration — the caller always shows a success message).
     */
    public function requestReset(string $email): void {
        $user = $this->userRepo->findByEmailGlobal($email);

        // Silent return — no error, no enumeration
        if ($user === null) {
            return;
        }

        if (!(bool) $user['is_active']) {
            return;
        }

        // Generate raw token and its HMAC-SHA256 hash
        $rawToken  = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = self::hashToken($rawToken);
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + self::TTL_SECONDS);

        // Store hashed token
        $this->resetRepo->insert($tokenHash, (string) $user['tenant_id'], (string) $user['id'], $expiresAt);

        // Build reset URL
        $baseUrl  = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8080'), '/');
        $resetUrl = $baseUrl . '/reset-password?token=' . $rawToken;

        // Build French HTML email body
        $name    = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
        $linkEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $html    = <<<HTML
            <p>Bonjour {$name},</p>
            <p>Vous avez demande la reinitialisation de votre mot de passe.</p>
            <p>Cliquez sur le lien ci-dessous (valable 1 heure) :</p>
            <p><a href="{$linkEsc}">{$linkEsc}</a></p>
            <p>Si vous n'etes pas a l'origine de cette demande, ignorez cet email.</p>
            HTML;

        $this->emailQueueRepo->enqueue(
            (string) $user['tenant_id'],
            (string) $user['email'],
            'Reinitialisation de votre mot de passe — AG-VOTE',
            $html,
        );
    }

    /**
     * Validates a raw token. Returns the password_resets row if valid, null otherwise.
     */
    public function validateToken(string $rawToken): ?array {
        $tokenHash = self::hashToken($rawToken);
        return $this->resetRepo->findByHash($tokenHash);
    }

    /**
     * Resets the user's password using a valid raw token.
     *
     * @return array{ok: bool, error: string|null}
     */
    public function resetPassword(string $rawToken, string $newPassword): array {
        $tokenHash = self::hashToken($rawToken);
        $row       = $this->resetRepo->findByHash($tokenHash);

        if ($row === null) {
            return ['ok' => false, 'error' => 'token_invalid'];
        }

        // Hash the new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user's password
        $this->userRepo->setPasswordHash(
            (string) $row['tenant_id'],
            (string) $row['user_id'],
            $passwordHash,
        );

        // Consume this token
        $this->resetRepo->markUsed($tokenHash);

        // Invalidate all other tokens for this user
        $this->resetRepo->deleteForUser((string) $row['user_id']);

        // Audit
        audit_log('password_reset', 'user', (string) $row['user_id'], [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        return ['ok' => true, 'error' => null];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Computes the HMAC-SHA256 hash of a raw token using APP_SECRET.
     */
    private static function hashToken(string $rawToken): string {
        return hash_hmac('sha256', $rawToken, APP_SECRET);
    }
}
