<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

/**
 * Centralizes session lifecycle management.
 *
 * Every session_start() in the app MUST go through this helper
 * to guarantee consistent, secure cookie parameters.
 */
final class SessionHelper {
    /**
     * Start a session with secure cookie defaults.
     *
     * Safe to call when session is already active — returns immediately.
     */
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params(self::cookieParams());
        session_start();
    }

    /**
     * Restart a session with fresh cookie parameters.
     *
     * Closes any existing session, applies secure cookie params,
     * then starts a new session. Use this when you need to guarantee
     * the cookie params are applied (e.g., after a prior bare session_start).
     */
    public static function restart(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_set_cookie_params(self::cookieParams());
        session_start();
    }

    /**
     * Destroy session completely: server-side data, cookie, and in-memory state.
     */
    public static function destroy(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(self::cookieParams());
            session_start();
        }

        $_SESSION = [];

        // Expire the session cookie with matching attributes (including SameSite)
        if (ini_get('session.use_cookies')) {
            $p = self::cookieParams();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }

        session_destroy();
    }

    /**
     * Canonical cookie parameters — single source of truth.
     *
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public static function cookieParams(): array {
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
