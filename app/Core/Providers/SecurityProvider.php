<?php

declare(strict_types=1);

namespace AgVote\Core\Providers;

use AgVote\Core\Security\AuthMiddleware;

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
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cache-Control: no-store');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        // CSP — no unsafe-inline for scripts (no inline <script> in templates).
        // style-src keeps unsafe-inline: 50+ dynamic inline styles in JS innerHTML.
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com; "
            . "connect-src 'self' ws: wss:; frame-ancestors 'self'; form-action 'self'");

        // HSTS in HTTPS (also detect TLS-terminating reverse proxy via X-Forwarded-Proto)
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if ($isHttps) {
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
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-CSRF-Token, X-Idempotency-Key');
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
     * Initialize auth middleware. RateLimiter is Redis-only since v1.0 Phase 1
     * (no configuration needed — Redis client is resolved at call time).
     */
    public static function init(bool $debug = false): void {
        AuthMiddleware::init(['debug' => $debug]);
    }
}
