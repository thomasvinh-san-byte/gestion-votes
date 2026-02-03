<?php
declare(strict_types=1);

namespace AgVote\Core;

/**
 * Logger - Logging structure pour AG-Vote.
 *
 * Fournit un logging JSON structure avec contexte utilisateur.
 * Compatible avec les standards PSR-3 (interface simplifiee).
 */
class Logger
{
    /** @var string Chemin du fichier de log (null = error_log PHP) */
    private static ?string $logFile = null;

    /** @var string Niveau minimum de log */
    private static string $minLevel = 'debug';

    /** @var array Niveaux de log et leur priorite */
    private const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /**
     * Configure le logger.
     */
    public static function configure(array $config = []): void
    {
        if (isset($config['file'])) {
            self::$logFile = $config['file'];
        }
        if (isset($config['level'])) {
            self::$minLevel = strtolower($config['level']);
        }
    }

    /**
     * Log un message de niveau DEBUG.
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    /**
     * Log un message de niveau INFO.
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /**
     * Log un message de niveau NOTICE.
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log('notice', $message, $context);
    }

    /**
     * Log un message de niveau WARNING.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /**
     * Log un message de niveau ERROR.
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Log un message de niveau CRITICAL.
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    /**
     * Log un message de niveau ALERT.
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log('alert', $message, $context);
    }

    /**
     * Log un message de niveau EMERGENCY.
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log('emergency', $message, $context);
    }

    /**
     * Log principal.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        // Verifier le niveau minimum
        if (!self::shouldLog($level)) {
            return;
        }

        $entry = self::buildEntry($level, $message, $context);
        self::write($entry);
    }

    /**
     * Log une exception avec stack trace.
     */
    public static function exception(\Throwable $e, array $context = []): void
    {
        $context['exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => self::formatTrace($e->getTrace()),
        ];

        if ($e->getPrevious()) {
            $context['exception']['previous'] = get_class($e->getPrevious()) . ': ' . $e->getPrevious()->getMessage();
        }

        self::error($e->getMessage(), $context);
    }

    /**
     * Log un acces API.
     */
    public static function api(string $method, string $uri, int $statusCode, float $duration, array $context = []): void
    {
        $context['http'] = [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
        ];

        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');
        self::log($level, "{$method} {$uri} {$statusCode}", $context);
    }

    /**
     * Log un evenement d'authentification.
     */
    public static function auth(string $event, bool $success, array $context = []): void
    {
        $context['auth_event'] = $event;
        $context['auth_success'] = $success;

        $level = $success ? 'info' : 'warning';
        $message = $success ? "Auth success: {$event}" : "Auth failure: {$event}";

        self::log($level, $message, $context);
    }

    /**
     * Log un evenement de securite.
     */
    public static function security(string $event, array $context = []): void
    {
        $context['security_event'] = $event;
        self::warning("Security: {$event}", $context);
    }

    /**
     * Verifie si le niveau doit etre logge.
     */
    private static function shouldLog(string $level): bool
    {
        $levelPriority = self::LEVELS[$level] ?? 0;
        $minPriority = self::LEVELS[self::$minLevel] ?? 0;
        return $levelPriority >= $minPriority;
    }

    /**
     * Construit l'entree de log.
     */
    private static function buildEntry(string $level, string $message, array $context): array
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'request_id' => self::getRequestId(),
        ];

        // Ajouter contexte utilisateur
        $user = self::getCurrentUser();
        if ($user) {
            $entry['user_id'] = $user['id'] ?? null;
            $entry['user_role'] = $user['role'] ?? null;
            $entry['tenant_id'] = $user['tenant_id'] ?? null;
        }

        // Ajouter infos de requete
        if (PHP_SAPI !== 'cli') {
            $entry['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];
        }

        // Fusionner le contexte
        if (!empty($context)) {
            $entry['context'] = $context;
        }

        return $entry;
    }

    /**
     * Ecrit l'entree de log.
     */
    private static function write(array $entry): void
    {
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (self::$logFile !== null) {
            $dir = dirname(self::$logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents(self::$logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            error_log($json);
        }
    }

    /**
     * Retourne un ID de requete unique.
     */
    private static function getRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID']
                ?? $_SERVER['HTTP_X_CORRELATION_ID']
                ?? substr(bin2hex(random_bytes(8)), 0, 16);
        }

        return $requestId;
    }

    /**
     * Recupere l'utilisateur courant.
     */
    private static function getCurrentUser(): ?array
    {
        // Utiliser AuthMiddleware si disponible
        if (class_exists(\AuthMiddleware::class) && method_exists(\AuthMiddleware::class, 'getCurrentUser')) {
            return \AuthMiddleware::getCurrentUser();
        }

        // Fallback sur la session
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_user'])) {
            return $_SESSION['auth_user'];
        }

        return null;
    }

    /**
     * Formate une stack trace pour le log.
     */
    private static function formatTrace(array $trace): array
    {
        $result = [];
        foreach (array_slice($trace, 0, 10) as $i => $frame) {
            $result[] = sprintf(
                '#%d %s%s%s() at %s:%d',
                $i,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0
            );
        }
        return $result;
    }

    /**
     * Reset pour les tests.
     */
    public static function reset(): void
    {
        self::$logFile = null;
        self::$minLevel = 'debug';
    }
}
