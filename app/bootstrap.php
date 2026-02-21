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

function config(string $key, mixed $default = null): mixed {
    return \AgVote\Core\Application::config($key) ?? $default;
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
        $repo = new \AgVote\Repository\AuditEventRepository();
        $repo->insert(
            \AgVote\Core\Security\AuthMiddleware::getCurrentTenantId(),
            $meetingId,
            \AgVote\Core\Security\AuthMiddleware::getCurrentUserId(),
            \AgVote\Core\Security\AuthMiddleware::getCurrentRole(),
            $action,
            $resourceType,
            $resourceId,
            $payload
        );
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
