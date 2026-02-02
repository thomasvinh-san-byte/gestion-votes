<?php
declare(strict_types=1);

/**
 * auth_login.php - Connexion utilisateur par email/mot de passe → session PHP
 *
 * POST /api/v1/auth_login.php
 * Body: { "email": "...", "password": "..." }
 *
 * Fallback legacy: { "api_key": "..." } (conservé pour compatibilité API)
 *
 * Crée une session PHP persistante.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\UserRepository;

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) $input = $_POST;

$userRepo = new UserRepository();
$user = null;
$authMethod = 'unknown';

// ── Authentification par email/mot de passe (prioritaire) ──
$email    = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email !== '' && $password !== '') {
    $authMethod = 'password';

    $user = $userRepo->findByEmailGlobal($email);

    if (!$user || empty($user['password_hash'])) {
        api_rate_limit('auth_login_fail', 10, 300);
        try {
            $userRepo->logAuthFailure(
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $email,
                'invalid_credentials'
            );
        } catch (\Throwable $e) { /* best effort */ }
        api_fail('invalid_credentials', 401, ['detail' => 'Email ou mot de passe incorrect.']);
    }

    if (!password_verify($password, $user['password_hash'])) {
        api_rate_limit('auth_login_fail', 10, 300);
        try {
            $userRepo->logAuthFailure(
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $email,
                'wrong_password'
            );
        } catch (\Throwable $e) { /* best effort */ }
        api_fail('invalid_credentials', 401, ['detail' => 'Email ou mot de passe incorrect.']);
    }

    // Rehash si l'algorithme par défaut a changé
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $userRepo->setPasswordHash($user['tenant_id'], $user['id'], $newHash);
        } catch (\Throwable $e) { /* best effort */ }
    }

} else {
    // ── Fallback : authentification par clé API (compatibilité) ──
    $apiKey = trim((string)($input['api_key'] ?? ''));

    if ($apiKey === '') {
        api_fail('missing_credentials', 400, ['detail' => 'Email et mot de passe requis.']);
    }

    $authMethod = 'api_key';
    $hash = hash_hmac('sha256', $apiKey, APP_SECRET);
    $user = $userRepo->findByApiKeyHashGlobal($hash);

    if (!$user) {
        api_rate_limit('auth_login_fail', 10, 300);
        try {
            $userRepo->logAuthFailure(
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                substr($apiKey, 0, 8) . '...',
                'invalid_key'
            );
        } catch (\Throwable $e) { /* best effort */ }
        api_fail('invalid_credentials', 401, ['detail' => 'Identifiants invalides.']);
    }
}

// ── Vérifications communes ──
if (empty($user['is_active'])) {
    api_fail('account_disabled', 403, ['detail' => 'Compte désactivé. Contactez un administrateur.']);
}

// ── Créer la session ──
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

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
    'method' => $authMethod,
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
