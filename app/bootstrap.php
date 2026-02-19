<?php
declare(strict_types=1);

/**
 * bootstrap.php - Unified entry point with integrated security
 *
 * REPLACEMENT for existing bootstrap.php.
 * Integrates: Config, PDO, CSRF, Auth RBAC, Rate Limiting, Security Headers.
 */

// =============================================================================
// 0. LOADING .env (minimal dotenv)
// =============================================================================

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!getenv($key)) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

// =============================================================================
// 1. AUTOLOADING (PSR-4 via Composer)
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
            'AgVote\\Core\\'              => __DIR__ . '/Core/',
            'AgVote\\Repository\\'        => __DIR__ . '/Repository/',
            'AgVote\\Service\\'           => __DIR__ . '/Services/',
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

// Import namespaced classes
use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\RateLimiter;

// Create global class aliases so api.php and other files can use short names
if (!class_exists('CsrfMiddleware', false)) {
    class_alias(CsrfMiddleware::class, 'CsrfMiddleware');
}
if (!class_exists('AuthMiddleware', false)) {
    class_alias(AuthMiddleware::class, 'AuthMiddleware');
}
if (!class_exists('RateLimiter', false)) {
    class_alias(RateLimiter::class, 'RateLimiter');
}

// =============================================================================
// 2. CONFIGURATION
// =============================================================================

$config = require __DIR__ . '/config.php';

// Application secret
if (!defined('APP_SECRET')) {
    $secret = getenv('APP_SECRET') ?: ($config['app_secret'] ?? 'change-me-in-prod');
    define('APP_SECRET', $secret);
}

// Secret validation in production
$isProduction = in_array(getenv('APP_ENV') ?: ($config['env'] ?? 'dev'), ['production', 'prod'], true);
$authEnabled = getenv('APP_AUTH_ENABLED') === '1' || strtolower((string)getenv('APP_AUTH_ENABLED')) === 'true';

if ($isProduction || $authEnabled) {
    if (APP_SECRET === 'change-me-in-prod' || strlen(APP_SECRET) < 32) {
        throw new RuntimeException(
            '[SECURITY] APP_SECRET must be set to a secure value (min 32 characters) in production. ' .
            'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
        );
    }
}

// Default tenant
if (!defined('DEFAULT_TENANT_ID')) {
    $tid = getenv('DEFAULT_TENANT_ID') ?: (getenv('TENANT_ID') ?: ($config['default_tenant_id'] ?? 'aaaaaaaa-1111-2222-3333-444444444444'));
    define('DEFAULT_TENANT_ID', $tid);
}

$GLOBALS['APP_TENANT_ID'] = DEFAULT_TENANT_ID;

$env = (string)($config['env'] ?? 'dev');
$debug = (bool)($config['debug'] ?? false);

// =============================================================================
// 3. ERROR HANDLING
// =============================================================================

if ($env === 'production' || $env === 'prod') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}
error_reporting(E_ALL);

set_exception_handler(function (\Throwable $e) use ($debug) {
    error_log("Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    $response = ['ok' => false, 'error' => 'internal_error'];
    if ($debug) {
        $response['debug'] = ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});

// =============================================================================
// 4. SECURITY HEADERS
// =============================================================================

// CSP + other security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CSP (permissive for HTMX/CDN, allows WebSocket via ws:/wss:)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' ws: wss:; frame-ancestors 'self'; form-action 'self'");
    
    // HSTS in HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// =============================================================================
// 5. CORS
// =============================================================================

$cors = $config['cors'] ?? [];
$allowed = $cors['allowed_origins'] ?? [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    if (!empty($cors['allow_credentials'])) {
        header('Access-Control-Allow-Credentials: true');
    }
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =============================================================================
// 6. DATABASE CONNECTION
// =============================================================================

$dsn  = (string)($config['db']['dsn']  ?? '');
$user = (string)($config['db']['user'] ?? '');
$pass = (string)($config['db']['pass'] ?? '');

if ($dsn === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'db_dsn_missing']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (\Throwable $e) {
    error_log("DB error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['ok' => false, 'error' => 'database_error'];
    if ($debug) $payload['detail'] = $e->getMessage();
    echo json_encode($payload);
    exit;
}

// =============================================================================
// 7. SECURITY INITIALIZATION
// =============================================================================

RateLimiter::configure([
    'storage_dir' => sys_get_temp_dir() . '/ag-vote-ratelimit',
]);

AuthMiddleware::init(['debug' => $debug]);

// =============================================================================
// 8. DATABASE HELPERS
// =============================================================================

function db(): PDO { global $pdo; return $pdo; }

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
// 9. AUDIT LOGGING
// =============================================================================

function audit_log(
    string $action,
    string $resourceType,
    ?string $resourceId = null,
    array $payload = [],
    ?string $meetingId = null
): void {
    try {
        $userId = AuthMiddleware::getCurrentUserId();
        $userRole = AuthMiddleware::getCurrentRole();
        $tenantId = AuthMiddleware::getCurrentTenantId();

        // Check if table exists with correct columns
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
// 10. UUID HELPERS
// =============================================================================

function api_uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// =============================================================================
// 11. WEBSOCKET AUTH TOKEN
// =============================================================================

/**
 * Generate an HMAC-signed token for WebSocket authentication.
 * Token format: base64( tenant_id:user_id:timestamp:hmac )
 * Valid for 5 minutes. Verified in app/WebSocket/Server.php.
 */
function ws_auth_token(?string $tenantId = null, ?string $userId = null): string {
    $tenantId = $tenantId ?: (AuthMiddleware::getCurrentTenantId() ?: DEFAULT_TENANT_ID);
    $userId = $userId ?: (AuthMiddleware::getCurrentUserId() ?: '');
    $ts = (string) time();
    $hmac = hash_hmac('sha256', "{$tenantId}|{$userId}|{$ts}", APP_SECRET);
    return base64_encode("{$tenantId}:{$userId}:{$ts}:{$hmac}");
}
