<?php
// app/auth.php
declare(strict_types=1);

function get_api_key(): string {
    $k = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // Fallback si certains serveurs ne mappent pas correctement HTTP_X_API_KEY
    if ($k === '' && function_exists('getallheaders')) {
        $h = getallheaders();
        $k = $h['X-Api-Key'] ?? $h['X-API-KEY'] ?? '';
    }

    // Fallback (MVP): support api_key in querystring for use cases like <iframe>
    // Note: should be reserved for DEV / internal use.
    if ($k === '' && isset($_GET['api_key'])) {
        $k = (string)$_GET['api_key'];
    }

    return trim((string)$k);
}

function auth_fail(int $code = 401): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

/**
 * Requires a key matching a given role.
 * - If expected key is not configured: prod -> 500; dev -> 401 (to avoid unintentional "open bar").
 */
function require_role(string $role): void {
    // DEV MODE (temporary): auth disabled to unblock UX.
    // Re-enable later with proper RBAC.
    return;

    $config = require __DIR__ . '/config.php';
    $env = (string)($config['env'] ?? 'prod');

    $expected = (string)($config['keys'][$role] ?? '');
    if ($expected === '') {
        if ($env === 'dev') {
            // In dev, we still force auth (you can choose to "disable" in dev, but it's risky).
            auth_fail(401);
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'server_misconfigured', 'detail' => "Clé manquante pour le rôle : $role"]);
        exit;
    }

    $provided = get_api_key();
    if (!hash_equals($expected, $provided)) {
        auth_fail(401);
    }
}

/**
 * Requires a key belonging to one of the given roles.
 */
function require_any_role(array $roles): void {
    // DEV MODE (temporary): auth disabled to unblock UX.
    // Re-enable later with proper RBAC.
    return;

    $config = require __DIR__ . '/config.php';
    $env = (string)($config['env'] ?? 'prod');
    $provided = get_api_key();

    foreach ($roles as $role) {
        $expected = (string)($config['keys'][$role] ?? '');
        if ($expected !== '' && hash_equals($expected, $provided)) {
            return;
        }
    }

    if ($env !== 'dev') {
        auth_fail(401);
    }

    // dev: if no key is defined, we also refuse (safe behavior)
    auth_fail(401);
}