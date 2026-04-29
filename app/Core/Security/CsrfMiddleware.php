<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Security\AuthMiddleware;

/**
 * CsrfMiddleware - OWASP-compliant CSRF Protection
 *
 * Implements the Synchronizer Token pattern with HTMX support.
 *
 * F10 (Phase 2 v2.1) adds opt-in *action-scoped* tokens on top of the
 * legacy session-wide token. A scoped token = HMAC(session_secret,
 * METHOD + '|' + PATH), so a token minted for `POST /meetings` does not
 * validate a `POST /admin_settings` even when leaked or replayed through
 * a cross-action XSS sink. Templates can opt into the new flow via
 * `CsrfMiddleware::fieldFor($method, $path)`. The legacy `field()` and
 * the session-wide token continue to work — `validate()` accepts either,
 * so no template needs to migrate atomically.
 */
final class CsrfMiddleware {
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_HEADER = 'X-CSRF-Token';
    private const TOKEN_LENGTH = 32;
    private const SCOPED_SECRET_KEY = 'csrf_scoped_secret';
    private const SCOPED_SECRET_BYTES = 32;

    /**
     * Initializes CSRF protection
     */
    public static function init(): void {
        SessionHelper::start();

        if (!self::hasValidToken()) {
            self::regenerateToken();
        }
    }

    private static function hasValidToken(): bool {
        if (!isset($_SESSION[self::TOKEN_NAME]) || !isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            return false;
        }
        return (time() - (int) $_SESSION[self::TOKEN_NAME . '_time']) < AuthMiddleware::getSessionTimeout();
    }

    public static function regenerateToken(): string {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_NAME . '_time'] = time();
        return $token;
    }

    public static function getToken(): string {
        self::init();
        if (!isset($_SESSION[self::TOKEN_NAME]) || !self::hasValidToken()) {
            return self::regenerateToken();
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Generates a hidden field for forms (legacy session-wide token).
     */
    public static function field(): string {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::TOKEN_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * F10: returns an action-scoped CSRF token bound to (METHOD, PATH).
     *
     * Token = HMAC-SHA256(session_scoped_secret, "POST|/api/v1/foo").
     * The scoped secret is generated once per session and stays server-side.
     * A leaked or replayed scoped token will fail validation on any other
     * action because the HMAC differs.
     *
     * Use case: high-value mutating endpoints (admin settings, delete,
     * password change) where defense-in-depth is wanted.
     *
     * Path normalization: strip query string and trailing slash so the
     * token survives `?foo=bar` and `/foo/` vs `/foo` mismatches.
     */
    public static function tokenFor(string $method, string $path): string {
        self::init();
        $secret = self::ensureScopedSecret();
        $action = strtoupper(trim($method)) . '|' . self::normalizePath($path);
        return hash_hmac('sha256', $action, $secret);
    }

    /**
     * F10: hidden form field bound to a specific (method, path).
     */
    public static function fieldFor(string $method, string $path): string {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::TOKEN_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::tokenFor($method, $path), ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * Validates CSRF token for mutating requests
     */
    public static function validate(bool $strict = true): bool {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Safe methods - no CSRF validation
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        self::init();

        $submitted = self::getSubmittedToken();

        if ($submitted === null) {
            if ($strict) {
                self::fail('csrf_token_missing');
            }
            return false;
        }

        $expected = (string) ($_SESSION[self::TOKEN_NAME] ?? '');
        $matchesLegacy = $expected !== '' && hash_equals($expected, $submitted);

        // F10: also accept an action-scoped token bound to (METHOD, current path).
        // We only compute the scoped match if a scoped secret already exists in
        // the session; a fresh session with no scoped tokens issued yet just
        // skips this branch (saving an unused HMAC).
        $matchesScoped = false;
        if (!$matchesLegacy && isset($_SESSION[self::SCOPED_SECRET_KEY])) {
            $rawPath = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $expectedScoped = self::tokenFor($method, $rawPath);
            $matchesScoped = hash_equals($expectedScoped, $submitted);
        }

        if (!$matchesLegacy && !$matchesScoped) {
            if ($strict) {
                self::fail('csrf_token_invalid');
            }
            return false;
        }

        if (!self::hasValidToken()) {
            if ($strict) {
                self::fail('csrf_token_expired');
            }
            return false;
        }

        // Extend token lifetime on successful validation (sliding window).
        // Both legacy and scoped paths refresh the timestamp.
        $_SESSION[self::TOKEN_NAME . '_time'] = time();

        return true;
    }

    private static function ensureScopedSecret(): string {
        if (!isset($_SESSION[self::SCOPED_SECRET_KEY]) || !is_string($_SESSION[self::SCOPED_SECRET_KEY])) {
            $_SESSION[self::SCOPED_SECRET_KEY] = bin2hex(random_bytes(self::SCOPED_SECRET_BYTES));
        }
        return (string) $_SESSION[self::SCOPED_SECRET_KEY];
    }

    private static function normalizePath(string $path): string {
        $q = strpos($path, '?');
        if ($q !== false) {
            $path = substr($path, 0, $q);
        }
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private static function getSubmittedToken(): ?string {
        // 1. Header X-CSRF-Token (AJAX/HTMX)
        $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper(self::TOKEN_HEADER));
        if (!empty($_SERVER[$headerKey])) {
            return trim((string) $_SERVER[$headerKey]);
        }

        // 2. getallheaders() fallback
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, self::TOKEN_HEADER) === 0) {
                    return trim((string) $value);
                }
            }
        }

        // 3. POST field
        if (isset($_POST[self::TOKEN_NAME])) {
            return trim((string) $_POST[self::TOKEN_NAME]);
        }

        // 4. JSON body (uses cached raw body to avoid consuming php://input)
        $rawBody = \AgVote\Core\Http\Request::getRawBody();
        if ($rawBody) {
            $json = json_decode($rawBody, true);
            if (is_array($json) && isset($json[self::TOKEN_NAME])) {
                return trim((string) $json[self::TOKEN_NAME]);
            }
        }

        return null;
    }

    private static function fail(string $code): never {
        error_log(sprintf(
            'CSRF failure: %s | IP: %s | URI: %s',
            $code,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
        ));

        throw new \AgVote\Core\Http\ApiResponseException(
            new \AgVote\Core\Http\JsonResponse(403, [
                'ok' => false,
                'error' => $code,
                'detail' => 'Validation CSRF échouée. Rechargez la page et réessayez.',
            ]),
        );
    }

    /**
     * Meta tag for JS retrieval
     */
    public static function metaTag(): string {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * JS code for HTMX and fetch
     */
    public static function jsSnippet(): string {
        $token = self::getToken();
        return <<<JS
            <script>
            (function(){
              const csrfToken = '{$token}';
              
              // HTMX: adds CSRF header
              document.body.addEventListener('htmx:configRequest', function(e) {
                e.detail.headers['X-CSRF-Token'] = csrfToken;
              });

              // Secure fetch wrapper
              window.secureFetch = function(url, options = {}) {
                options.headers = options.headers || {};
                options.headers['X-CSRF-Token'] = csrfToken;
                options.credentials = options.credentials || 'same-origin';
                return fetch(url, options);
              };

              // Expose for manual use
              window.CSRF = { token: csrfToken, header: 'X-CSRF-Token', name: 'csrf_token' };
            })();
            </script>
            JS;
    }

    public static function getTokenName(): string {
        return self::TOKEN_NAME;
    }

    public static function getHeaderName(): string {
        return self::TOKEN_HEADER;
    }
}
