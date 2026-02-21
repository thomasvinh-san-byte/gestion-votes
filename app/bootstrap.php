<?php
declare(strict_types=1);

/**
 * bootstrap.php — Application bootstrap.
 *
 * Thin wrapper that loads autoloading and delegates to Application::boot().
 * All initialization logic lives in providers under app/Core/Providers/.
 */

// =============================================================================
// AUTOLOADING (PSR-4 via Composer)
// =============================================================================

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Fallback PSR-4 autoloader (works without composer install)
    spl_autoload_register(function (string $class): void {
        $map = [
            'AgVote\\Core\\Security\\'    => __DIR__ . '/Core/Security/',
            'AgVote\\Core\\Validation\\'  => __DIR__ . '/Core/Validation/',
            'AgVote\\Core\\Providers\\'   => __DIR__ . '/Core/Providers/',
            'AgVote\\Core\\Http\\'        => __DIR__ . '/Core/Http/',
            'AgVote\\Core\\'              => __DIR__ . '/Core/',
            'AgVote\\Controller\\'        => __DIR__ . '/Controller/',
            'AgVote\\Repository\\'        => __DIR__ . '/Repository/',
            'AgVote\\Service\\'           => __DIR__ . '/Services/',
            'AgVote\\View\\'              => __DIR__ . '/View/',
            'AgVote\\WebSocket\\'         => __DIR__ . '/WebSocket/',
        ];
        foreach ($map as $prefix => $dir) {
            $len = strlen($prefix);
            if (strncmp($class, $prefix, $len) === 0) {
                $file = $dir . str_replace('\\', '/', substr($class, $len)) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    });
}

// =============================================================================
// BOOT APPLICATION
// =============================================================================

\AgVote\Core\Application::boot();

// =============================================================================
// GLOBAL HELPER FUNCTIONS
// These remain as global functions for backward compatibility.
// They delegate to proper classes internally.
// =============================================================================

function db(): PDO {
    return \AgVote\Core\Providers\DatabaseProvider::pdo();
}

/** @deprecated Use a Repository instead. */
function db_select_one(string $sql, array $params = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** @deprecated Alias for db_select_one(). Use a Repository. */
function db_one(string $sql, array $params = []): ?array {
    return db_select_one($sql, $params);
}

/** @deprecated Use a Repository instead. */
function db_select_all(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** @deprecated Alias for db_select_all(). Use a Repository. */
function db_all(string $sql, array $params = []): array {
    return db_select_all($sql, $params);
}

/** @deprecated Use a Repository instead. */
function db_execute(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** @deprecated Alias for db_execute(). Use a Repository. */
function db_exec(string $sql, array $params = []): int {
    return db_execute($sql, $params);
}

/** @deprecated Use a Repository instead. */
function db_scalar(string $sql, array $params = []): mixed {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

// =============================================================================
// AUDIT LOGGING
// =============================================================================

function audit_log(
    string $action,
    string $resourceType,
    ?string $resourceId = null,
    array $payload = [],
    ?string $meetingId = null
): void {
    try {
        $userId = \AgVote\Core\Security\AuthMiddleware::getCurrentUserId();
        $userRole = \AgVote\Core\Security\AuthMiddleware::getCurrentRole();
        $tenantId = \AgVote\Core\Security\AuthMiddleware::getCurrentTenantId();

        $sql = "INSERT INTO audit_events
                (tenant_id, meeting_id, actor_user_id, actor_role, action, resource_type, resource_id, payload, created_at)
                VALUES (:tid, :mid, :uid, :role, :action, :rtype, :rid, :payload::jsonb, NOW())";

        db()->prepare($sql)->execute([
            ':tid' => $tenantId,
            ':mid' => $meetingId,
            ':uid' => $userId,
            ':role' => $userRole,
            ':action' => $action,
            ':rtype' => $resourceType,
            ':rid' => $resourceId,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (\Throwable $e) {
        error_log("audit_log failed: " . $e->getMessage());
    }
}

// =============================================================================
// UUID HELPERS
// =============================================================================

function api_uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// =============================================================================
// WEBSOCKET AUTH TOKEN
// =============================================================================

/**
 * Generate an HMAC-signed token for WebSocket authentication.
 */
function ws_auth_token(?string $tenantId = null, ?string $userId = null): string {
    $tenantId = $tenantId ?: (\AgVote\Core\Security\AuthMiddleware::getCurrentTenantId() ?: DEFAULT_TENANT_ID);
    $userId = $userId ?: (\AgVote\Core\Security\AuthMiddleware::getCurrentUserId() ?: '');
    $ts = (string) time();
    $hmac = hash_hmac('sha256', "{$tenantId}|{$userId}|{$ts}", APP_SECRET);
    return base64_encode("{$tenantId}:{$userId}:{$ts}:{$hmac}");
}
