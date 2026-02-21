<?php

declare(strict_types=1);

namespace AgVote\Core\Providers;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\RateLimiter;

/**
 * Security initialization provider.
 *
 * Sends security headers, handles CORS, and initializes auth/rate-limit.
 */
final class SecurityProvider {
    /**
     * Send standard security headers (CSP, HSTS, etc.).
     */
    public static function headers(): void {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // CSP (permissive for HTMX/CDN, allows WebSocket via ws:/wss:)
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; "
            . "img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com; "
            . "connect-src 'self' ws: wss:; frame-ancestors 'self'; form-action 'self'");

        // HSTS in HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Handle CORS preflight and response headers.
     */
    public static function cors(array $corsConfig): void {
        $allowed = $corsConfig['allowed_origins'] ?? [];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-CSRF-Token');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            if (!empty($corsConfig['allow_credentials'])) {
                header('Access-Control-Allow-Credentials: true');
            }
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Initialize auth middleware and rate limiter.
     */
    public static function init(bool $debug = false): void {
        RateLimiter::configure([
            'storage_dir' => sys_get_temp_dir() . '/ag-vote-ratelimit',
        ]);

        AuthMiddleware::init(['debug' => $debug]);
    }
}
