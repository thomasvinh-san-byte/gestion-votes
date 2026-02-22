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
    require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
    $pdo = db();
    $pdo->query('SELECT 1');
    $checks['database'] = true;
} catch (Throwable $e) {
    $status = 'degraded';
    $checks['database'] = false;
    $checks['db_error'] = $e->getMessage();
}

$httpCode = $status === 'ok' ? 200 : 503;
http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'checks' => $checks,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE);
