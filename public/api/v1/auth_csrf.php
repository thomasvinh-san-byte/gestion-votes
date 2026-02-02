<?php
declare(strict_types=1);

/**
 * GET /api/v1/auth_csrf.php
 * Retourne un token CSRF valide pour la session courante.
 * Utilisé par le frontend (HTMX/fetch) et pour le testing CLI.
 */

require __DIR__ . '/../../../app/api.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_fail('method_not_allowed', 405);
}

CsrfMiddleware::init();
$token = CsrfMiddleware::getToken();

api_ok([
    'csrf_token' => $token,
    'header_name' => CsrfMiddleware::getHeaderName(),
    'field_name' => CsrfMiddleware::getTokenName(),
]);
