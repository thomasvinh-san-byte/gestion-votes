<?php
declare(strict_types=1);

/**
 * api.php - API helpers with integrated security
 *
 * REPLACEMENT for existing api.php.
 * Integrates: CSRF validation, Auth RBAC, Rate Limiting.
 */

require __DIR__ . '/bootstrap.php';

// =============================================================================
// CACHE php://input (can only be read once)
// Both CSRF middleware and api_request() need it.
// =============================================================================
if (!isset($GLOBALS['__ag_vote_raw_body'])) {
    $GLOBALS['__ag_vote_raw_body'] = file_get_contents('php://input') ?: '';
}

// =============================================================================
// API FUNCTIONS - JSON RESPONSES
// =============================================================================

function api_ok(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_fail(string $error, int $code = 400, array $extra = []): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    // In production, strip internal error details from 5xx responses to avoid leaking DB/stack info
    if (($_ENV['APP_ENV'] ?? 'demo') === 'production' && $code >= 500) {
        unset($extra['detail']);
    }

    // Enrich with translated French message
    $enriched = \AgVote\Service\ErrorDictionary::enrichError($error, $extra);

    echo json_encode(['ok' => false, 'error' => $error] + $enriched, JSON_UNESCAPED_UNICODE);
    exit;
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

    // Parse JSON or POST body (uses global cache)
    $raw = $GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input');
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
    $mt = db_select_one(
        "SELECT validated_at FROM meetings WHERE tenant_id = :tid AND id = :mid",
        [':tid' => api_current_tenant_id(), ':mid' => $meetingId]
    );
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
    $mt = db_select_one(
        "SELECT * FROM meetings WHERE tenant_id = :tid AND id = :mid",
        [':tid' => api_current_tenant_id(), ':mid' => $meetingId]
    );
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
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

