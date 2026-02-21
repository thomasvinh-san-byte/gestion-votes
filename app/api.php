<?php
declare(strict_types=1);

/**
 * api.php - API helpers with integrated security
 *
 * REPLACEMENT for existing api.php.
 * Integrates: CSRF validation, Auth RBAC, Rate Limiting.
 */

require_once __DIR__ . '/bootstrap.php';

// =============================================================================
// GLOBAL EXCEPTION HANDLER for ApiResponseException
// Ensures api_ok()/api_fail() responses are sent even when not dispatched
// through the Router (e.g., direct file access in public/api/v1/).
// =============================================================================
set_exception_handler(function (\Throwable $e) {
    if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
        $e->getResponse()->send();
        return;
    }
    // Unexpected uncaught exception — generic 500
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'internal_error'], JSON_UNESCAPED_UNICODE);
});

// Eagerly cache php://input (can only be read once)
\AgVote\Core\Http\Request::getRawBody();

// =============================================================================
// API FUNCTIONS - JSON RESPONSES
// =============================================================================

function api_ok(array $data = [], int $code = 200): never {
    throw new \AgVote\Core\Http\ApiResponseException(
        \AgVote\Core\Http\JsonResponse::ok($data, $code)
    );
}

function api_fail(string $error, int $code = 400, array $extra = []): never {
    throw new \AgVote\Core\Http\ApiResponseException(
        \AgVote\Core\Http\JsonResponse::fail($error, $code, $extra)
    );
}

// =============================================================================
// API FUNCTIONS - VALIDATION
// =============================================================================

function api_is_uuid(string $v): bool {
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $v
    );
}

function api_require_uuid(array $in, string $key): string {
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '' || !api_is_uuid($v)) {
        api_fail('missing_or_invalid_uuid', 400, ['field' => $key, 'expected' => 'uuid']);
    }
    return $v;
}

// =============================================================================
// API FUNCTIONS - AUTHENTICATION & AUTHORIZATION
// =============================================================================

/**
 * Requires a role to access the resource.
 * Also validates CSRF token for POST/PUT/PATCH/DELETE requests.
 *
 * @param string|array $roles Allowed role(s). 'public' = no auth required.
 */
function api_require_role(string|array $roles): void {
    $roles = is_array($roles) ? $roles : [$roles];
    
    // CSRF validation for mutating requests (unless disabled)
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $csrfEnabled = getenv('CSRF_ENABLED') !== '0';
    
    if ($csrfEnabled && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        // 'public' role or voter endpoints = no CSRF (vote token serves as proof)
        if (!in_array('public', $roles, true) && !in_array('voter', $roles, true)) {
            CsrfMiddleware::validate();
        }
    }

    // Role verification
    AuthMiddleware::requireRole($roles);
}

// =============================================================================
// API FUNCTIONS - REQUEST PARSING
// =============================================================================

/**
 * Returns the current HTTP method (uppercase).
 */
function api_method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Parse and validate incoming request.
 * Accepts one or more allowed methods: api_request('GET'), api_request('GET', 'POST').
 * Defaults to GET if no method specified.
 */
function api_request(string ...$methods): array {
    $method = api_method();

    $allowed = !empty($methods) ? array_map('strtoupper', $methods) : ['GET'];

    if (!in_array($method, $allowed, true)) {
        api_fail('method_not_allowed', 405, [
            'detail' => "Méthode {$method} non autorisée, " . implode('/', $allowed) . " attendu."
        ]);
    }

    // Parse JSON or POST body
    $raw = \AgVote\Core\Http\Request::getRawBody();
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        $data = $_POST;
    }

    // Merge with GET
    return array_merge($_GET, $data);
}

// =============================================================================
// API FUNCTIONS - CURRENT USER
// =============================================================================

function api_current_user(): ?array {
    return AuthMiddleware::getCurrentUser();
}

function api_current_user_id(): ?string {
    return AuthMiddleware::getCurrentUserId();
}

function api_current_role(): string {
    return AuthMiddleware::getCurrentRole();
}

function api_current_tenant_id(): string {
    return AuthMiddleware::getCurrentTenantId();
}

// =============================================================================
// API FUNCTIONS - BUSINESS GUARDS
// =============================================================================

/**
 * Checks that a meeting is not validated (modification forbidden post-validation).
 * Fatal 409 if meeting is validated.
 */
function api_guard_meeting_not_validated(string $meetingId): void {
    if ($meetingId === '') return;
    $st = db()->prepare("SELECT validated_at FROM meetings WHERE tenant_id = :tid AND id = :mid");
    $st->execute([':tid' => api_current_tenant_id(), ':mid' => $meetingId]);
    $mt = $st->fetch();
    if ($mt && !empty($mt['validated_at'])) {
        api_fail('meeting_validated', 409, [
            'detail' => 'Séance validée : modification interdite (séance figée).'
        ]);
    }
}

/**
 * Checks that a meeting exists and returns it.
 * Fatal 404 if not found.
 */
function api_guard_meeting_exists(string $meetingId): array {
    $st = db()->prepare("SELECT * FROM meetings WHERE tenant_id = :tid AND id = :mid");
    $st->execute([':tid' => api_current_tenant_id(), ':mid' => $meetingId]);
    $mt = $st->fetch();
    if (!$mt) {
        api_fail('meeting_not_found', 404);
    }
    return $mt;
}

// =============================================================================
// API FUNCTIONS - RATE LIMITING
// =============================================================================

/**
 * Apply rate limiting
 */
function api_rate_limit(string $context, int $maxAttempts = 100, int $windowSeconds = 60): void {
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // If authenticated, use user ID
    $userId = api_current_user_id();
    if ($userId) {
        $identifier = $userId;
    }
    
    RateLimiter::check($context, $identifier, $maxAttempts, $windowSeconds);
}

// =============================================================================
// API FUNCTIONS - TRANSACTIONS
// =============================================================================

/**
 * Execute a callback within a database transaction.
 * Automatically commits on success, rolls back on exception.
 *
 * @param callable $fn Function to execute within transaction
 * @return mixed Return value of the callback
 * @throws \Throwable Re-throws any exception after rollback
 */
function api_transaction(callable $fn): mixed {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (\AgVote\Core\Http\ApiResponseException $e) {
        // api_ok() inside a transaction = success → commit, then re-throw
        if ($pdo->inTransaction()) {
            $e->getResponse()->getStatusCode() < 400
                ? $pdo->commit()
                : $pdo->rollBack();
        }
        throw $e;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

