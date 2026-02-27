<?php

declare(strict_types=1);

namespace AgVote\Core;

use AgVote\Core\Providers\DatabaseProvider;
use AgVote\Core\Providers\EnvProvider;
use AgVote\Core\Providers\RedisProvider;
use AgVote\Core\Providers\SecurityProvider;
use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Core\Security\RateLimiter;
use AgVote\Event\Listener\WebSocketListener;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Application bootstrap orchestrator.
 *
 * Replaces the monolithic bootstrap.php with a structured boot sequence.
 * Providers are called in dependency order.
 */
final class Application {
    private static bool $booted = false;
    private static array $config = [];
    private static bool $debug = false;
    private static ?EventDispatcherInterface $dispatcher = null;

    /**
     * Boot the application. Idempotent — safe to call multiple times.
     */
    public static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // 1. Environment variables
        EnvProvider::load(dirname(__DIR__, 2) . '/.env');

        // 2. Autoloading (already loaded before Application is called)

        // 3. Class aliases for backward compatibility
        self::registerClassAliases();

        // 4. Configuration
        self::loadConfig();

        // 5. Error handling
        self::configureErrors();

        // 6. Security headers
        SecurityProvider::headers();

        // 7. CORS
        SecurityProvider::cors(self::$config['cors'] ?? []);

        // 8. Database
        DatabaseProvider::connect(self::$config['db'] ?? [], self::$debug);

        // 9. Auth & rate limiter
        SecurityProvider::init(self::$debug);

        // 10. Redis (optional — graceful if unavailable)
        RedisProvider::configure(self::$config['redis'] ?? []);

        // 11. Event dispatcher
        self::initEventDispatcher();
    }

    /**
     * Boot for CLI context (no HTTP headers, CORS, or security headers).
     * Used by bin/console commands.
     */
    public static function bootCli(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        EnvProvider::load(dirname(__DIR__, 2) . '/.env');
        self::registerClassAliases();
        self::loadConfig();
        self::configureErrors();
        DatabaseProvider::connect(self::$config['db'] ?? [], self::$debug);
        RedisProvider::configure(self::$config['redis'] ?? []);
        self::initEventDispatcher();
    }

    /**
     * Get configuration value.
     */
    public static function config(?string $key = null): mixed {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }

    public static function isDebug(): bool {
        return self::$debug;
    }

    /**
     * Get the event dispatcher instance.
     */
    public static function dispatcher(): EventDispatcherInterface {
        if (self::$dispatcher === null) {
            self::initEventDispatcher();
        }
        return self::$dispatcher;
    }

    // ── Internal methods ────────────────────────────────────────────────

    private static function registerClassAliases(): void {
        if (!class_exists('CsrfMiddleware', false)) {
            class_alias(CsrfMiddleware::class, 'CsrfMiddleware');
        }
        if (!class_exists('AuthMiddleware', false)) {
            class_alias(AuthMiddleware::class, 'AuthMiddleware');
        }
        if (!class_exists('RateLimiter', false)) {
            class_alias(RateLimiter::class, 'RateLimiter');
        }
    }

    private static function loadConfig(): void {
        self::$config = require dirname(__DIR__) . '/config.php';

        // APP_SECRET
        if (!defined('APP_SECRET')) {
            $secret = getenv('APP_SECRET') ?: (self::$config['app_secret'] ?? 'change-me-in-prod');
            define('APP_SECRET', $secret);
        }

        // Validate secret in production / demo / when auth is enabled
        $env = getenv('APP_ENV') ?: (self::$config['env'] ?? 'dev');
        $isProduction = in_array($env, ['production', 'prod', 'demo'], true);
        $authEnabled = getenv('APP_AUTH_ENABLED') === '1'
            || strtolower((string) getenv('APP_AUTH_ENABLED')) === 'true';

        $insecureSecrets = [
            'change-me-in-prod',
            'dev-secret-do-not-use-in-production-change-me-now-please-64chr',
        ];

        if (($isProduction || $authEnabled) && (in_array(APP_SECRET, $insecureSecrets, true) || strlen(APP_SECRET) < 32)) {
            throw new RuntimeException(
                '[SECURITY] APP_SECRET must be set to a secure value (min 32 characters) in production. '
                . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"',
            );
        }

        // Block APP_AUTH_ENABLED=0 in production — authentication cannot be disabled
        if ($isProduction && !$authEnabled) {
            throw new RuntimeException(
                '[SECURITY] APP_AUTH_ENABLED cannot be disabled in production. '
                . 'Set APP_AUTH_ENABLED=1 or remove it (auth is enabled by default).',
            );
        }

        // DEFAULT_TENANT_ID
        if (!defined('DEFAULT_TENANT_ID')) {
            $tid = getenv('DEFAULT_TENANT_ID')
                ?: (getenv('TENANT_ID') ?: (self::$config['default_tenant_id'] ?? 'aaaaaaaa-1111-2222-3333-444444444444'));
            define('DEFAULT_TENANT_ID', $tid);
        }

        self::$debug = (bool) (self::$config['debug'] ?? false);
    }

    private static function initEventDispatcher(): void {
        if (self::$dispatcher !== null) {
            return;
        }
        self::$dispatcher = new EventDispatcher();
        WebSocketListener::subscribe(self::$dispatcher);
    }

    private static function configureErrors(): void {
        $env = (string) (self::$config['env'] ?? 'dev');

        if ($env === 'production' || $env === 'prod') {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        } else {
            ini_set('display_errors', '1');
        }
        error_reporting(E_ALL);

        $debug = self::$debug;
        set_exception_handler(function (Throwable $e) use ($debug) {
            error_log('Uncaught exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }

            $response = ['ok' => false, 'error' => 'internal_error'];
            if ($debug) {
                $response['debug'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        });
    }
}
