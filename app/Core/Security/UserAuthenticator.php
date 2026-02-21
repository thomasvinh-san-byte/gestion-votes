<?php
declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Repository\UserRepository;

/**
 * UserAuthenticator - Handles authentication logic.
 *
 * Extracted from AuthMiddleware to separate authentication concerns
 * from permission/authorization logic.
 *
 * Supports:
 * - API key authentication (X-Api-Key header)
 * - PHP session authentication (with timeout)
 * - Dev bypass (when APP_AUTH_ENABLED=0)
 */
final class UserAuthenticator
{
    private const SESSION_TIMEOUT = 1800; // 30 minutes

    /**
     * Authenticate the current request.
     * Returns the user array or null if not authenticated.
     */
    public static function authenticate(): ?array
    {
        // Bypass if auth disabled (DEV only)
        if (!self::isAuthEnabled()) {
            return [
                'id' => 'dev-user',
                'role' => 'admin',
                'name' => 'Dev User (Auth Disabled)',
                'tenant_id' => self::getDefaultTenantId(),
            ];
        }

        // 1. API Key (header)
        $apiKey = self::extractApiKey();
        if ($apiKey !== null) {
            $user = self::findUserByApiKey($apiKey);
            if ($user !== null) {
                return $user;
            }
        }

        // 2. Session PHP
        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['auth_user'])) {
                $lastActivity = $_SESSION['auth_last_activity'] ?? 0;
                $now = time();

                if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TIMEOUT) {
                    error_log(sprintf(
                        'SESSION_EXPIRED | user_id=%s | idle=%ds',
                        $_SESSION['auth_user']['id'] ?? 'unknown',
                        $now - $lastActivity
                    ));
                    $_SESSION = [];
                    session_destroy();
                    return null;
                }

                $_SESSION['auth_last_activity'] = $now;
                return $_SESSION['auth_user'];
            }
        }

        return null;
    }

    public static function isAuthEnabled(): bool
    {
        $env = getenv('APP_AUTH_ENABLED');
        return $env === '1' || strtolower((string)$env) === 'true';
    }

    public static function generateApiKey(): array
    {
        $key = bin2hex(random_bytes(32));
        $hash = hash_hmac('sha256', $key, self::getAppSecret());
        return ['key' => $key, 'hash' => $hash];
    }

    public static function hashApiKey(string $key): string
    {
        return hash_hmac('sha256', $key, self::getAppSecret());
    }

    // ── Internal ────────────────────────────────────────────────────────

    private static function extractApiKey(): ?string
    {
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'X-Api-Key') === 0 || strcasecmp($k, 'X-API-KEY') === 0) {
                    $key = (string)$v;
                    break;
                }
            }
        }
        $key = trim($key);
        return $key !== '' ? $key : null;
    }

    private static function findUserByApiKey(string $apiKey): ?array
    {
        $hash = hash_hmac('sha256', $apiKey, self::getAppSecret());

        try {
            $repo = new UserRepository();
            $row = $repo->findByApiKeyHashGlobal($hash);

            if (!$row) {
                self::logFailure('invalid_api_key', $apiKey);
                return null;
            }

            if (!$row['is_active']) {
                self::logFailure('user_inactive', $apiKey);
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            error_log("API key lookup error: " . $e->getMessage());
            return null;
        }
    }

    private static function logFailure(string $reason, ?string $credential = null): void
    {
        error_log(sprintf(
            "AUTH_FAILURE | reason=%s | ip=%s | uri=%s",
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
    }

    private static function getAppSecret(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');

        if (!$secret || $secret === 'change-me-in-prod' || strlen($secret) < 32) {
            if (self::isAuthEnabled()) {
                throw new \RuntimeException(
                    '[SECURITY] APP_SECRET must be set to a secure value (min 32 characters). '
                    . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
                );
            }
            error_log('[WARNING] Using insecure APP_SECRET in dev mode. Do NOT use in production.');
            return 'dev-secret-not-for-production-' . str_repeat('x', 32);
        }

        return $secret;
    }

    private static function getDefaultTenantId(): string
    {
        return defined('DEFAULT_TENANT_ID')
            ? DEFAULT_TENANT_ID
            : 'aaaaaaaa-1111-2222-3333-444444444444';
    }
}
