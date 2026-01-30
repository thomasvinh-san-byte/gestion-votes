<?php
/**
 * @deprecated This service is replaced by AuthMiddleware.php.
 *             Kept for backward compatibility only. Do not add new logic here.
 * @see \AuthMiddleware
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class AuthService
{
    public static function enabled(): bool
    {
        $v = getenv('APP_AUTH_ENABLED');
        return ($v === '1' || strtolower((string)$v) === 'true');
    }

    public static function requireRole(string $role): void
    {
        if (!self::enabled()) return;

        $key = self::getKeyFromRequest();
        if ($key === null) self::deny('missing_api_key');

        $user = self::findUserByKey($key);
        if (!$user) self::deny('invalid_api_key');

        if (!$user['is_active']) self::deny('user_inactive');

        $uRole = (string)$user['role'];
        if ($uRole !== $role && $uRole !== 'admin') {
            self::deny('forbidden');
        }

        $GLOBALS['AUTH_USER'] = $user;
    }

    public static function getKeyFromRequest(): ?string
    {
        $hdr = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $hdr = trim((string)$hdr);
        return $hdr !== '' ? $hdr : null;
    }

    public static function hashKey(string $key): string
    {
        return hash_hmac('sha256', $key, APP_SECRET);
    }

    public static function findUserByKey(string $key): ?array
    {
        $hash = self::hashKey($key);
        $row = db_select_one(
            "SELECT id, tenant_id, email, name, role, is_active
             FROM users
             WHERE tenant_id = ? AND api_key_hash = ?
             LIMIT 1",
            [DEFAULT_TENANT_ID, $hash]
        );
        return $row ?: null;
    }

    private static function deny(string $code): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>$code]);
        exit;
    }
}
