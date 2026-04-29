<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * ClientIp — trusted resolution of the originating client IP and HTTPS state.
 *
 * Why this exists:
 *   `$_SERVER['HTTP_X_FORWARDED_FOR']` and `$_SERVER['HTTP_X_FORWARDED_PROTO']`
 *   are caller-supplied. Trusting them blindly lets any HTTP client spoof
 *   their IP for rate-limiting purposes (and lie about HTTPS to flip the
 *   `Secure` cookie flag). We only honor those headers when the immediate
 *   peer (`REMOTE_ADDR`) is a known reverse proxy listed in `TRUSTED_PROXIES`.
 *
 * Configuration:
 *   `TRUSTED_PROXIES` — comma-separated env var of IP addresses that the
 *   application accepts forwarded headers from. Empty / unset → the headers
 *   are ignored and the raw socket peer IP is used.
 *
 * Example:
 *   TRUSTED_PROXIES="10.0.0.1,10.0.0.2"
 *
 * Behavior matrix:
 *
 *   | REMOTE_ADDR in TRUSTED_PROXIES | XFF present | get() returns          |
 *   |--------------------------------|-------------|------------------------|
 *   | yes                            | yes         | first XFF entry        |
 *   | yes                            | no          | REMOTE_ADDR            |
 *   | no                             | any         | REMOTE_ADDR (XFF lost) |
 *   | unknown                        | any         | "unknown"              |
 *
 * The same trust gate guards `isHttps()` for `X-Forwarded-Proto`.
 */
final class ClientIp {
    /** @var list<string>|null Cached trusted proxies list (parsed once per request). */
    private static ?array $trustedCache = null;

    /**
     * Resolve the originating client IP.
     */
    public static function get(): string {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote === '') {
            return 'unknown';
        }

        if (!self::isTrustedProxy($remote)) {
            return $remote;
        }

        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff === '') {
            return $remote;
        }

        $first = trim(explode(',', $xff)[0] ?? '');
        if ($first === '' || filter_var($first, FILTER_VALIDATE_IP) === false) {
            return $remote;
        }

        return $first;
    }

    /**
     * Whether the request is HTTPS, honoring X-Forwarded-Proto from trusted proxies only.
     */
    public static function isHttps(): bool {
        $native = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
        if ($native) {
            return true;
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote === '' || !self::isTrustedProxy($remote)) {
            return false;
        }

        $proto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        return strtolower($proto) === 'https';
    }

    /**
     * Reset the cached trusted-proxies list — only for tests that mutate the env.
     */
    public static function reset(): void {
        self::$trustedCache = null;
    }

    private static function isTrustedProxy(string $remote): bool {
        $trusted = self::trustedProxies();
        return in_array($remote, $trusted, true);
    }

    /**
     * @return list<string>
     */
    private static function trustedProxies(): array {
        if (self::$trustedCache !== null) {
            return self::$trustedCache;
        }

        $raw = (string) (getenv('TRUSTED_PROXIES') ?: ($_SERVER['TRUSTED_PROXIES'] ?? ''));
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn(string $p): bool => $p !== '');

        $valid = [];
        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP) !== false) {
                $valid[] = $part;
            }
        }

        return self::$trustedCache = array_values($valid);
    }
}
