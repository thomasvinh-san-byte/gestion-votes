<?php
declare(strict_types=1);

/**
 * api.php - Helpers API avec sécurité intégrée
 * 
 * REMPLACEMENT du api.php existant.
 * Intègre : CSRF validation, Auth RBAC, Rate Limiting.
 */

require __DIR__ . '/bootstrap.php';

// =============================================================================
// CACHE php://input (ne peut être lu qu'une seule fois)
// Le CSRF middleware et api_request() en ont tous les deux besoin.
// =============================================================================
if (!isset($GLOBALS['__ag_vote_raw_body'])) {
    $GLOBALS['__ag_vote_raw_body'] = file_get_contents('php://input') ?: '';
}

// =============================================================================
// FONCTIONS API - RÉPONSES JSON
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
    echo json_encode(['ok' => false, 'error' => $error] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok(array $data = [], int $code = 200): never {
    json_ok($data, $code);
}

function api_fail(string $error, int $code = 400, array $extra = []): never {
    json_err($error, $code, $extra);
}

// =============================================================================
// FONCTIONS API - VALIDATION
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
// FONCTIONS API - AUTHENTIFICATION & AUTORISATION
// =============================================================================

/**
 * Exige un rôle pour accéder à la ressource.
 * Valide également le token CSRF pour les requêtes POST/PUT/PATCH/DELETE.
 * 
 * @param string|array $roles Rôle(s) autorisé(s). 'public' = pas d'auth requise.
 */
function api_require_role(string|array $roles): void {
    $roles = is_array($roles) ? $roles : [$roles];
    
    // Validation CSRF pour les requêtes mutantes (sauf si désactivée)
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $csrfEnabled = getenv('CSRF_ENABLED') !== '0';
    
    if ($csrfEnabled && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        // Rôle 'public' ou endpoints votant = pas de CSRF (token vote fait office)
        if (!in_array('public', $roles, true) && !in_array('voter', $roles, true)) {
            CsrfMiddleware::validate();
        }
    }

    // Vérification du rôle
    AuthMiddleware::requireRole($roles);
}

/**
 * Alias pour compatibilité avec l'ancien système
 */
function require_role(string $role): void {
    api_require_role($role);
}

/**
 * Alias pour compatibilité avec l'ancien système
 */
function require_any_role(array $roles): void {
    api_require_role($roles);
}

// =============================================================================
// FONCTIONS API - PARSING REQUÊTE
// =============================================================================

/**
 * Parse et valide la requête entrante
 */
function api_request(string $expectedMethod = 'GET'): array {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method !== strtoupper($expectedMethod)) {
        api_fail('method_not_allowed', 405, [
            'detail' => "Méthode {$method} non autorisée, {$expectedMethod} attendu."
        ]);
    }

    // Parse le body JSON ou POST (utilise le cache global)
    $raw = $GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        $data = $_POST;
    }

    // Fusionne avec GET
    return array_merge($_GET, $data);
}

// =============================================================================
// FONCTIONS API - UTILISATEUR COURANT
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
// FONCTIONS API - GARDES MÉTIER
// =============================================================================

/**
 * Vérifie qu'une séance n'est pas validée (interdiction de modification post-validation).
 * Fatal 409 si la séance est validée.
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
 * Vérifie qu'une séance existe et la retourne.
 * Fatal 404 si absente.
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
// FONCTIONS API - RATE LIMITING
// =============================================================================

/**
 * Applique le rate limiting
 */
function api_rate_limit(string $context, int $maxAttempts = 100, int $windowSeconds = 60): void {
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Si authentifié, utiliser l'user ID
    $userId = api_current_user_id();
    if ($userId) {
        $identifier = $userId;
    }
    
    RateLimiter::check($context, $identifier, $maxAttempts, $windowSeconds);
}

// =============================================================================
// FONCTIONS API - VALIDATION AVANCÉE
// =============================================================================

/**
 * Crée un validateur pour les entrées
 */
function api_validator(): InputValidator {
    return InputValidator::schema();
}

/**
 * Valide les entrées et échoue si invalide
 */
function api_validate(array $input, InputValidator $validator): array {
    $result = $validator->validate($input);
    if (!$result->isValid()) {
        api_fail('validation_failed', 422, ['errors' => $result->errors()]);
    }
    return $result->data();
}
