<?php

declare(strict_types=1);

namespace AgVote\Core\Providers;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Database connection provider.
 *
 * Creates and holds the PDO singleton. Provides the db() accessor.
 */
final class DatabaseProvider {
    private static ?PDO $pdo = null;

    /**
     * Connect to the database using config values.
     */
    public static function connect(array $dbConfig, bool $debug = false): PDO {
        $dsn = (string) ($dbConfig['dsn'] ?? '');
        $user = (string) ($dbConfig['user'] ?? '');
        $pass = (string) ($dbConfig['pass'] ?? '');

        if ($dsn === '') {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'db_dsn_missing']);
            exit;
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (Throwable $e) {
            error_log('DB error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            $payload = ['ok' => false, 'error' => 'database_error'];
            if ($debug) {
                $payload['detail'] = $e->getMessage();
            }
            echo json_encode($payload);
            exit;
        }

        return self::$pdo;
    }

    /**
     * Get the PDO instance.
     */
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            throw new RuntimeException('Database not connected. Call DatabaseProvider::connect() first.');
        }
        return self::$pdo;
    }
}
