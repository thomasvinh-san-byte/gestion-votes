<?php
declare(strict_types=1);

namespace AgVote\Core\Security;

/**
 * SecurityHeaders - HTTP security headers
 *
 * Implements OWASP-recommended headers:
 * - Content-Security-Policy (CSP)
 * - Strict-Transport-Security (HSTS)
 * - X-Frame-Options
 * - X-Content-Type-Options
 * - X-XSS-Protection
 * - Referrer-Policy
 * - Permissions-Policy
 */
final class SecurityHeaders
{
    /** @var array Default configuration */
    private static array $defaults = [
        'csp' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "https://unpkg.com", "https://cdn.tailwindcss.com", "https://cdnjs.cloudflare.com"],
            'style-src' => ["'self'", "'unsafe-inline'", "https://cdn.tailwindcss.com"],
            'img-src' => ["'self'", "data:", "blob:"],
            'font-src' => ["'self'", "https://fonts.gstatic.com"],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ],
        'hsts' => [
            'max-age' => 31536000, // 1 year
            'includeSubDomains' => true,
            'preload' => false,
        ],
        'frame_options' => 'SAMEORIGIN',
        'content_type_options' => 'nosniff',
        'xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => [
            'geolocation' => [],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
        ],
    ];

    /** @var array Configuration active */
    private static array $config = [];

    /** @var bool Headers already sent */
    private static bool $sent = false;

    /**
     * Configures headers (call before send())
     */
    public static function configure(array $config): void
    {
        self::$config = array_replace_recursive(self::$defaults, $config);
    }

    /**
     * Sends all security headers
     */
    public static function send(): void
    {
        if (self::$sent || headers_sent()) {
            return;
        }

        $config = empty(self::$config) ? self::$defaults : self::$config;

        // Content-Security-Policy
        if (!empty($config['csp'])) {
            self::sendCsp($config['csp']);
        }

        // Strict-Transport-Security (HTTPS only)
        if (!empty($config['hsts']) && self::isHttps()) {
            self::sendHsts($config['hsts']);
        }

        // X-Frame-Options
        if (!empty($config['frame_options'])) {
            header('X-Frame-Options: ' . $config['frame_options']);
        }

        // X-Content-Type-Options
        if (!empty($config['content_type_options'])) {
            header('X-Content-Type-Options: ' . $config['content_type_options']);
        }

        // X-XSS-Protection (legacy, but still useful for old browsers)
        if (!empty($config['xss_protection'])) {
            header('X-XSS-Protection: ' . $config['xss_protection']);
        }

        // Referrer-Policy
        if (!empty($config['referrer_policy'])) {
            header('Referrer-Policy: ' . $config['referrer_policy']);
        }

        // Permissions-Policy
        if (!empty($config['permissions_policy'])) {
            self::sendPermissionsPolicy($config['permissions_policy']);
        }

        self::$sent = true;
    }

    /**
     * Envoie le header CSP
     */
    private static function sendCsp(array $csp): void
    {
        $directives = [];

        foreach ($csp as $directive => $values) {
            if (is_array($values)) {
                $directives[] = $directive . ' ' . implode(' ', $values);
            } else {
                $directives[] = $directive . ' ' . $values;
            }
        }

        $policy = implode('; ', $directives);
        header('Content-Security-Policy: ' . $policy);
    }

    /**
     * Envoie le header HSTS
     */
    private static function sendHsts(array $hsts): void
    {
        $value = 'max-age=' . ($hsts['max-age'] ?? 31536000);

        if (!empty($hsts['includeSubDomains'])) {
            $value .= '; includeSubDomains';
        }

        if (!empty($hsts['preload'])) {
            $value .= '; preload';
        }

        header('Strict-Transport-Security: ' . $value);
    }

    /**
     * Envoie le header Permissions-Policy
     */
    private static function sendPermissionsPolicy(array $permissions): void
    {
        $directives = [];

        foreach ($permissions as $feature => $allowlist) {
            if (empty($allowlist)) {
                $directives[] = $feature . '=()';
            } else {
                $quoted = array_map(fn($v) => '"' . $v . '"', $allowlist);
                $directives[] = $feature . '=(' . implode(' ', $quoted) . ')';
            }
        }

        header('Permissions-Policy: ' . implode(', ', $directives));
    }

    /**
     * Detects if the connection is HTTPS
     */
    private static function isHttps(): bool
    {
        // Direct HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Behind a proxy (X-Forwarded-Proto)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Port 443
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * Specific headers for JSON API responses
     */
    public static function sendApiHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Specific headers for file downloads
     */
    public static function sendDownloadHeaders(string $filename, string $mimeType = 'application/octet-stream'): void
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Headers to prevent caching
     */
    public static function sendNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Adds a CSP nonce for inline scripts
     *
     * @return string The generated nonce
     */
    public static function generateNonce(): string
    {
        static $nonce = null;
        
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        
        return $nonce;
    }

    /**
     * Modifie la CSP pour ajouter un nonce
     */
    public static function addCspNonce(string $nonce): void
    {
        $config = empty(self::$config) ? self::$defaults : self::$config;
        
        $config['csp']['script-src'][] = "'nonce-{$nonce}'";
        $config['csp']['style-src'][] = "'nonce-{$nonce}'";
        
        self::$config = $config;
    }

    /**
     * Retourne le nonce courant pour utilisation dans les templates
     */
    public static function getNonce(): string
    {
        return self::generateNonce();
    }

    /**
     * Generates a nonce attribute for script/style tags
     */
    public static function nonceAttr(): string
    {
        return 'nonce="' . htmlspecialchars(self::generateNonce(), ENT_QUOTES, 'UTF-8') . '"';
    }
}
