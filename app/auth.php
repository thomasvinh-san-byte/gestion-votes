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

    return trim((string) $k);
}

function auth_fail(int $code = 401): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// Legacy require_role() / require_any_role() removed.
// Auth is now handled by AuthMiddleware::requireRole() via the router.
