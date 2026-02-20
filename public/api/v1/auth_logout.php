<?php
declare(strict_types=1);

/**
 * auth_logout.php - Déconnexion utilisateur (destruction session)
 *
 * POST /api/v1/auth_logout.php
 *
 * Requires CSRF token validation for security.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Core\Security\CsrfMiddleware;

api_request('POST');

// Validate CSRF token to prevent forced logout attacks
CsrfMiddleware::validate();

// Audit avant destruction
$userId = api_current_user_id();
if ($userId) {
    audit_log('user_logout', 'user', $userId, [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
}

// Détruire la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Réinitialiser AuthMiddleware
AuthMiddleware::reset();

api_ok(['logged_out' => true]);
