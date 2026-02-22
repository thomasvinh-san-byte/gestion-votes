<?php

/**
 * Health check endpoint for container orchestration (Docker, Render, etc.).
 *
 * Verifies:
 *  1. PHP-FPM is responsive (implicit — this file is executing)
 *  2. Database is reachable (SELECT 1)
 *
 * Does NOT require authentication or CSRF.
 * Returns HTTP 200 + JSON on success, HTTP 503 on failure.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$status = 'ok';
$checks = ['php' => true, 'database' => false];

try {
    // Minimal DB check — avoids full Application::boot() overhead
    // (security headers, CORS, Redis, event dispatcher, etc.)
    $dsn = getenv('DB_DSN') ?: '';
    $user = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'vote_app';
    $pass = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

    if ($dsn === '') {
        throw new RuntimeException('DB_DSN not configured');
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $pdo->query('SELECT 1');
    $checks['database'] = true;
} catch (Throwable $e) {
    $status = 'degraded';
    $checks['database'] = false;
    // Never leak connection details to unauthenticated callers.
    error_log('health: db check failed: ' . $e->getMessage());
}

$httpCode = $status === 'ok' ? 200 : 503;
http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'checks' => $checks,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE);
