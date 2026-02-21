<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

/**
 * CsrfMiddleware - OWASP-compliant CSRF Protection
 *
 * Implements the Synchronizer Token pattern with HTMX support.
 */
final class CsrfMiddleware {
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_HEADER = 'X-CSRF-Token';
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Initializes CSRF protection
     */
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }

        if (!self::hasValidToken()) {
            self::regenerateToken();
        }
    }

    private static function startSecureSession(): void {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    private static function hasValidToken(): bool {
        if (!isset($_SESSION[self::TOKEN_NAME]) || !isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            return false;
        }
        return (time() - (int) $_SESSION[self::TOKEN_NAME . '_time']) < self::TOKEN_LIFETIME;
    }

    public static function regenerateToken(): string {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_NAME . '_time'] = time();
        return $token;
    }

    public static function getToken(): string {
        self::init();
        return $_SESSION[self::TOKEN_NAME] ?? self::regenerateToken();
    }

    /**
     * Generates a hidden field for forms
     */
    public static function field(): string {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::TOKEN_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8'),
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

        $expected = $_SESSION[self::TOKEN_NAME] ?? '';

        if (!hash_equals($expected, $submitted)) {
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

        return true;
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
