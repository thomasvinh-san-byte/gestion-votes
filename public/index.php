<?php

declare(strict_types=1);

/**
 * Front controller for gestion-votes.
 *
 * Dispatches API requests via the central Router. Falls back to file-based
 * routing for any URI not registered in the route table (backward compat).
 *
 * Usage (with URL rewriting):
 *   All requests → public/index.php → Router::dispatch()
 *
 * Without URL rewriting (current state):
 *   Each .php file in public/api/v1/ still works directly.
 *   This front controller is opt-in via .htaccess / nginx config.
 */

// ─── Resolve the request URI ───────────────────────────────────────────────

$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$uri = rtrim($uri, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ─── Determine bootstrap type ──────────────────────────────────────────────

$appDir = dirname(__DIR__) . '/app';

// Special routes that don't need api.php (e.g., doc_content)
$rawRoutes = [
    '/api/v1/doc_content' => __DIR__ . '/api/v1/doc_content.php',
];

$uriWithoutPhp = preg_replace('/\.php$/', '', $uri);

if (isset($rawRoutes[$uri]) || isset($rawRoutes[$uriWithoutPhp])) {
    $file = $rawRoutes[$uri] ?? $rawRoutes[$uriWithoutPhp];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// ─── Bootstrap routes (email tracking: bootstrap.php only) ─────────────────

require_once $appDir . '/bootstrap.php';

// Load the Router
$router = new \AgVote\Core\Router();
$configureRoutes = require $appDir . '/routes.php';
$configureRoutes($router);

// Check if this is a bootstrap-only route (before loading api.php)
$bootstrapRoutes = [
    '/api/v1/email_pixel',
    '/api/v1/email_redirect',
];

$isBootstrap = in_array($uri, $bootstrapRoutes, true)
    || in_array($uriWithoutPhp, $bootstrapRoutes, true);

if (!$isBootstrap) {
    // Signal that the Router is handling middleware (prevents double enforcement in api.php)
    define('AG_ROUTER_ACTIVE', true);
    // Load the full API layer (session, auth, CSRF, api_* helpers)
    require_once $appDir . '/api.php';
}

// ─── Dispatch via Router ───────────────────────────────────────────────────

if ($router->dispatch($method, $uri)) {
    exit;
}

// ─── Fallback: file-based routing ──────────────────────────────────────────

// Map URI to a file in public/api/v1/
$candidate = $uriWithoutPhp . '.php';
$filePath = __DIR__ . $candidate;

if (file_exists($filePath) && is_file($filePath)) {
    try {
        require $filePath;
    } catch (\AgVote\Core\Http\ApiResponseException $e) {
        $e->getResponse()->send();
    }
    exit;
}

// Nothing matched
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => false,
    'error' => 'not_found',
], JSON_UNESCAPED_UNICODE);
