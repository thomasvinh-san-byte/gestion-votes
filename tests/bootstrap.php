<?php
declare(strict_types=1);

/**
 * Bootstrap pour les tests PHPUnit
 */

// Chemin racine du projet
define('PROJECT_ROOT', dirname(__DIR__));

// Autoload Composer si disponible
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Définir les constantes de test
if (!defined('APP_SECRET')) {
    define('APP_SECRET', 'test-secret-for-unit-tests-only-32chars!');
}

if (!defined('DEFAULT_TENANT_ID')) {
    define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
}

// Variables d'environnement de test
putenv('APP_ENV=testing');
putenv('APP_DEBUG=1');
putenv('APP_AUTH_ENABLED=0');

// Stub db() for tests — prevents "Call to undefined function db()" errors
// when repositories are instantiated without explicit PDO injection.
if (!function_exists('db')) {
    function db(): PDO {
        throw new \RuntimeException('No database connection available in test environment. Inject a PDO mock via constructor.');
    }
}

// Stub api_transaction() for tests — executes the callable directly without
// a real database transaction.
if (!function_exists('api_transaction')) {
    function api_transaction(callable $fn): mixed {
        return $fn();
    }
}

// Stub config() for tests — returns sensible defaults for unit tests.
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return match ($key) {
            'proxy_max_per_receiver' => 99,
            default => $default,
        };
    }
}

// Stub audit_log() for tests — no-op in test environment.
if (!function_exists('audit_log')) {
    function audit_log(string $action, string $resourceType, ?string $resourceId, array $data = [], ?string $meetingId = null): void {
        // no-op
    }
}

// Stub api_uuid4() for tests — returns a deterministic v4-format UUID.
if (!function_exists('api_uuid4')) {
    function api_uuid4(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }
}

// Stub API helper functions for controller tests — these replicate the
// behaviour from app/api.php but are safe for unit tests (no exit(), no
// bootstrap.php side-effects).

if (!function_exists('api_ok')) {
    function api_ok(array $data = [], int $code = 200): never {
        throw new \AgVote\Core\Http\ApiResponseException(
            \AgVote\Core\Http\JsonResponse::ok($data, $code),
        );
    }
}

if (!function_exists('api_fail')) {
    function api_fail(string $error, int $code = 400, array $extra = []): never {
        throw new \AgVote\Core\Http\ApiResponseException(
            \AgVote\Core\Http\JsonResponse::fail($error, $code, $extra),
        );
    }
}

if (!function_exists('api_method')) {
    function api_method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}

if (!function_exists('api_request')) {
    function api_request(string ...$methods): array {
        $method = api_method();
        $allowed = !empty($methods) ? array_map('strtoupper', $methods) : ['GET'];
        if (!in_array($method, $allowed, true)) {
            api_fail('method_not_allowed', 405, [
                'detail' => "Méthode {$method} non autorisée, " . implode('/', $allowed) . ' attendu.',
            ]);
        }
        $raw = \AgVote\Core\Http\Request::getRawBody();
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        return array_merge($_GET, $data);
    }
}

if (!function_exists('api_query')) {
    function api_query(string $key, string $default = ''): string {
        return trim((string) ($_GET[$key] ?? $default));
    }
}

if (!function_exists('api_query_int')) {
    function api_query_int(string $key, int $default = 0): int {
        return (int) ($_GET[$key] ?? $default);
    }
}

if (!function_exists('api_is_uuid')) {
    function api_is_uuid(string $v): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $v,
        );
    }
}

if (!function_exists('api_require_uuid')) {
    function api_require_uuid(array $in, string $key): string {
        $v = trim((string) ($in[$key] ?? ''));
        if ($v === '' || !api_is_uuid($v)) {
            api_fail('missing_or_invalid_uuid', 400, ['field' => $key, 'expected' => 'uuid']);
        }
        return $v;
    }
}

if (!function_exists('api_current_user_id')) {
    function api_current_user_id(): ?string {
        return \AgVote\Core\Security\AuthMiddleware::getCurrentUserId();
    }
}

if (!function_exists('api_current_role')) {
    function api_current_role(): string {
        return \AgVote\Core\Security\AuthMiddleware::getCurrentRole();
    }
}

if (!function_exists('api_current_tenant_id')) {
    function api_current_tenant_id(): string {
        return \AgVote\Core\Security\AuthMiddleware::getCurrentTenantId();
    }
}

if (!function_exists('api_guard_meeting_not_validated')) {
    function api_guard_meeting_not_validated(string $meetingId): void {
        // no-op in test environment — meeting validation checks require DB
    }
}

if (!function_exists('api_guard_meeting_exists')) {
    function api_guard_meeting_exists(string $meetingId): array {
        // Delegates to RepositoryFactory so injectRepos() mocks are respected.
        $repo = \AgVote\Core\Providers\RepositoryFactory::getInstance()->meeting();
        $mt = $repo->findByIdForTenant($meetingId, \AgVote\Core\Security\AuthMiddleware::getCurrentTenantId());
        if (!$mt) {
            api_fail('meeting_not_found', 404);
        }
        return $mt;
    }
}

if (!function_exists('api_require_role')) {
    function api_require_role(string|array $roles): void {
        // no-op in test environment
    }
}

// Register class alias for non-namespaced AuthMiddleware (used by AuthController)
if (!class_exists('AuthMiddleware', false)) {
    class_alias(\AgVote\Core\Security\AuthMiddleware::class, 'AuthMiddleware');
}

// Stub api_file() for tests — replicates the behaviour from app/api.php.
// Used by ResolutionDocumentController and other file upload endpoints.
if (!function_exists('api_file')) {
    function api_file(string ...$keys): ?array {
        foreach ($keys as $key) {
            if (!empty($_FILES[$key]) && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                return $_FILES[$key];
            }
        }
        return null;
    }
}

// Explicitly require ControllerTestCase so it is always available regardless of
// file-load order. PHPUnit loads test files alphabetically; without this,
// DashboardControllerTest (D) would fail because the class is declared in
// ControllerTestCase.php (C) but PHP's require_once order is non-deterministic
// across different PHPUnit invocation modes.
require_once __DIR__ . '/Unit/ControllerTestCase.php';

// Use namespaced classes
use AgVote\Core\Security\RateLimiter;

// Configure RateLimiter for tests
RateLimiter::configure([
    'storage_dir' => sys_get_temp_dir() . '/ag-vote-test-ratelimit',
]);
