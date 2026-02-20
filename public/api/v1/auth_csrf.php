<?php
declare(strict_types=1);

/**
 * GET /api/v1/auth_csrf.php
 * Retourne un token CSRF valide pour la session courante.
 * Utilisé par le frontend (HTMX/fetch) et pour le testing CLI.
 */

require __DIR__ . '/../../../app/api.php';

api_request('GET');

CsrfMiddleware::init();
$token = CsrfMiddleware::getToken();

api_ok([
    'csrf_token' => $token,
    'header_name' => CsrfMiddleware::getHeaderName(),
    'field_name' => CsrfMiddleware::getTokenName(),
]);
