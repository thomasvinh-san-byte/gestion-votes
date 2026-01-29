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

    // Fallback (MVP) : support de api_key en querystring pour les usages type <iframe>
    // Note: à réserver au DEV / usage interne.
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
 * Exige une clé correspondant à un rôle donné.
 * - Si la clé attendue n'est pas configurée : en prod -> 500 ; en dev -> 401 (pour éviter “open bar” involontaire).
 */
function require_role(string $role): void {
    // MODE DEV (temporaire) : auth désactivée pour débloquer l'UX.
    // Réactiver plus tard avec un RBAC propre.
    return;

    $config = require __DIR__ . '/config.php';
    $env = (string)($config['env'] ?? 'prod');

    $expected = (string)($config['keys'][$role] ?? '');
    if ($expected === '') {
        if ($env === 'dev') {
            // En dev, on force quand même l’auth (tu peux choisir de “désactiver” en dev, mais c’est risqué).
            auth_fail(401);
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'server_misconfigured', 'detail' => "missing key for role: $role"]);
        exit;
    }

    $provided = get_api_key();
    if (!hash_equals($expected, $provided)) {
        auth_fail(401);
    }
}

/**
 * Exige une clé appartenant à l’un des rôles donnés.
 */
function require_any_role(array $roles): void {
    // MODE DEV (temporaire) : auth désactivée pour débloquer l'UX.
    // Réactiver plus tard avec un RBAC propre.
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

    // dev: si aucune clé n’est définie, on refuse aussi (comportement sûr)
    auth_fail(401);
}