<?php
declare(strict_types=1);

/**
 * CsrfMiddleware - Protection CSRF conforme OWASP
 * 
 * Implémente le pattern Synchronizer Token avec support HTMX.
 */
final class CsrfMiddleware
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_HEADER = 'X-CSRF-Token';
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 heure

    /**
     * Initialise la protection CSRF
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }

        if (!self::hasValidToken()) {
            self::regenerateToken();
        }
    }

    private static function startSecureSession(): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    private static function hasValidToken(): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || !isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            return false;
        }
        return (time() - (int)$_SESSION[self::TOKEN_NAME . '_time']) < self::TOKEN_LIFETIME;
    }

    public static function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_NAME . '_time'] = time();
        return $token;
    }

    public static function getToken(): string
    {
        self::init();
        return $_SESSION[self::TOKEN_NAME] ?? self::regenerateToken();
    }

    /**
     * Génère un champ hidden pour formulaire
     */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::TOKEN_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Valide le token CSRF pour les requêtes mutantes
     */
    public static function validate(bool $strict = true): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Méthodes safe - pas de validation CSRF
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

    private static function getSubmittedToken(): ?string
    {
        // 1. Header X-CSRF-Token (AJAX/HTMX)
        $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper(self::TOKEN_HEADER));
        if (!empty($_SERVER[$headerKey])) {
            return trim((string)$_SERVER[$headerKey]);
        }

        // 2. getallheaders() fallback
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, self::TOKEN_HEADER) === 0) {
                    return trim((string)$value);
                }
            }
        }

        // 3. POST field
        if (isset($_POST[self::TOKEN_NAME])) {
            return trim((string)$_POST[self::TOKEN_NAME]);
        }

        // 4. JSON body (utilise le cache global pour eviter de consommer php://input)
        $rawBody = $GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input');
        if ($rawBody) {
            $json = json_decode($rawBody, true);
            if (is_array($json) && isset($json[self::TOKEN_NAME])) {
                return trim((string)$json[self::TOKEN_NAME]);
            }
        }

        return null;
    }

    private static function fail(string $code): never
    {
        error_log(sprintf(
            "CSRF failure: %s | IP: %s | URI: %s",
            $code,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => $code,
            'detail' => 'Validation CSRF échouée. Rechargez la page et réessayez.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Meta tag pour récupération JS
     */
    public static function metaTag(): string
    {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Code JS pour HTMX et fetch
     */
    public static function jsSnippet(): string
    {
        $token = self::getToken();
        return <<<JS
<script>
(function(){
  const csrfToken = '{$token}';
  
  // HTMX: ajoute le header CSRF
  document.body.addEventListener('htmx:configRequest', function(e) {
    e.detail.headers['X-CSRF-Token'] = csrfToken;
  });

  // Wrapper fetch sécurisé
  window.secureFetch = function(url, options = {}) {
    options.headers = options.headers || {};
    options.headers['X-CSRF-Token'] = csrfToken;
    options.credentials = options.credentials || 'same-origin';
    return fetch(url, options);
  };

  // Expose pour usage manuel
  window.CSRF = { token: csrfToken, header: 'X-CSRF-Token', name: 'csrf_token' };
})();
</script>
JS;
    }

    public static function getTokenName(): string
    {
        return self::TOKEN_NAME;
    }

    public static function getHeaderName(): string
    {
        return self::TOKEN_HEADER;
    }
}
