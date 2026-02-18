<?php
declare(strict_types=1);

/**
 * api.php - API helpers with integrated security
 *
 * REPLACEMENT for existing api.php.
 * Integrates: CSRF validation, Auth RBAC, Rate Limiting.
 */

require __DIR__ . '/bootstrap.php';

use AgVote\Core\Validation\InputValidator;

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

function json_ok(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $error, int $code = 400, array $extra = []): never {
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

function api_ok(array $data = [], int $code = 200): never {
    json_ok($data, $code);
}

function api_fail(string $error, int $code = 400, array $extra = []): never {
    json_err($error, $code, $extra);
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

/**
 * Checks if a string looks like a slug (non-UUID, alphanumeric with hyphens).
 */
function api_is_slug(string $v): bool {
    return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{2,48}[a-zA-Z0-9]$/', $v)
        && !api_is_uuid($v);
}

/**
 * Checks if a string is a valid identifier (UUID or slug).
 */
function api_is_identifier(string $v): bool {
    return api_is_uuid($v) || api_is_slug($v);
}

function api_require_uuid(array $in, string $key): string {
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '' || !api_is_uuid($v)) {
        api_fail('missing_or_invalid_uuid', 400, ['field' => $key, 'expected' => 'uuid']);
    }
    return $v;
}

/**
 * Requires an identifier (UUID or slug) in input data.
 * Allows URL obfuscation while supporting legacy UUIDs.
 */
function api_require_identifier(array $in, string $key): string {
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '' || !api_is_identifier($v)) {
        api_fail('missing_or_invalid_identifier', 400, ['field' => $key, 'expected' => 'uuid ou slug']);
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

/**
 * Alias for backward compatibility
 */
function require_role(string $role): void {
    api_require_role($role);
}

/**
 * Alias for backward compatibility
 */
function require_any_role(array $roles): void {
    api_require_role($roles);
}

/**
 * Alias api_require_any_role -> api_require_role (accepts array)
 */
function api_require_any_role(string|array $roles): void {
    api_require_role($roles);
}

// =============================================================================
// API FUNCTIONS - REQUEST PARSING
// =============================================================================

/**
 * Parse and validate incoming request
 */
function api_request(string $expectedMethod = 'GET'): array {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method !== strtoupper($expectedMethod)) {
        api_fail('method_not_allowed', 405, [
            'detail' => "Méthode {$method} non autorisée, {$expectedMethod} attendu."
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
// API FUNCTIONS - ADVANCED VALIDATION
// =============================================================================

/**
 * Creates a validator for inputs
 */
function api_validator(): InputValidator {
    return InputValidator::schema();
}

/**
 * Validates inputs and fails if invalid
 */
function api_validate(array $input, InputValidator $validator): array {
    $result = $validator->validate($input);
    if (!$result->isValid()) {
        api_fail('validation_failed', 422, ['errors' => $result->errors()]);
    }
    return $result->data();
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

/**
 * Execute endpoint logic with standardized error handling.
 * Catches exceptions and returns appropriate API errors.
 *
 * @param callable $fn Function containing endpoint logic
 */
function api_handle(callable $fn): void {
    try {
        $fn();
    } catch (\InvalidArgumentException $e) {
        api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
    } catch (\RuntimeException $e) {
        api_fail('business_error', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
        error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        api_fail('internal_error', 500);
    }
}

/**
 * Execute endpoint logic within a transaction with error handling.
 * Combines api_transaction() and api_handle().
 *
 * @param callable $fn Function containing endpoint logic
 */
function api_transactional(callable $fn): void {
    api_handle(function() use ($fn) {
        api_transaction($fn);
    });
}
