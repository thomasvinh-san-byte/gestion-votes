<?php
declare(strict_types=1);

/**
 * auth_login.php - Connexion utilisateur par clé API → session PHP
 *
 * POST /api/v1/auth_login.php
 * Body: { "api_key": "..." }
 *
 * Crée une session PHP persistante pour éviter de renvoyer la clé à chaque requête.
 */

require __DIR__ . '/../../../app/api.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) $input = $_POST;

$apiKey = trim((string)($input['api_key'] ?? ''));

if ($apiKey === '') {
    api_fail('missing_api_key', 400, ['detail' => 'Clé API requise.']);
}

// Hash HMAC-SHA256 (cohérent avec AuthMiddleware::findUserByApiKey)
$hash = hash_hmac('sha256', $apiKey, APP_SECRET);

$user = db_select_one(
    "SELECT id, tenant_id, email, name, role, is_active
     FROM users
     WHERE api_key_hash = :hash AND tenant_id = :tid",
    [':hash' => $hash, ':tid' => api_current_tenant_id()]
);

if (!$user) {
    // Rate limit sur les tentatives échouées
    api_rate_limit('auth_login_fail', 10, 300);

    // Log l'échec
    try {
        db_execute(
            "INSERT INTO auth_failures (ip, user_agent, key_prefix, reason, created_at)
             VALUES (:ip, :ua, :prefix, 'invalid_key', NOW())",
            [
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                ':prefix' => substr($apiKey, 0, 8) . '...',
            ]
        );
    } catch (\Throwable $e) { /* best effort */ }

    api_fail('invalid_api_key', 401, ['detail' => 'Clé API invalide.']);
}

if (empty($user['is_active'])) {
    api_fail('account_disabled', 403, ['detail' => 'Compte désactivé. Contactez un administrateur.']);
}

// Créer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Régénérer l'ID de session pour éviter session fixation
session_regenerate_id(true);

$_SESSION['auth_user'] = [
    'id' => $user['id'],
    'tenant_id' => $user['tenant_id'],
    'email' => $user['email'],
    'name' => $user['name'],
    'role' => $user['role'],
    'is_active' => $user['is_active'],
    'logged_in_at' => date('c'),
];

// Audit
audit_log('user_login', 'user', $user['id'], [
    'method' => 'api_key',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

api_ok([
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
    ],
    'session' => true,
]);
