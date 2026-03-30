<?php
/**
 * Health check endpoint for container orchestration (Docker, Render, etc.).
 *
 * Verifies:
 *  1. PHP-FPM is responsive (implicit — this file is executing)
 *  2. Database is reachable (SELECT 1)
 *  3. Redis is reachable (PING)
 *  4. Filesystem is writable (touch test file in upload dir)
 *
 * Does NOT require authentication or CSRF.
 * Returns HTTP 200 + JSON on success, HTTP 503 on failure.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks = [
    "database" => false,
    "redis" => false,
    "filesystem" => false,
];

// --- Database check ---
try {
    $dsn = getenv('DB_DSN') ?: '';
    $user = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'vote_app';
    $pass = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

    if ($dsn === '') {
        throw new RuntimeException('DB_DSN not configured');
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->query('SELECT 1');
    $checks['database'] = true;
} catch (Throwable $e) {
    error_log('health: db check failed: ' . $e->getMessage());
}

// --- Redis check ---
try {
    $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
    $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
    $redisPass = getenv('REDIS_PASSWORD') ?: null;

    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort, 2.0); // 2 second timeout
        if ($redisPass) {
            $redis->auth($redisPass);
        }
        $pong = $redis->ping();
        // Redis::ping() returns true or '+PONG' depending on phpredis version
        $checks['redis'] = ($pong === true || $pong === '+PONG');
        $redis->close();
    } else {
        // Redis extension not loaded — mark as unavailable but not a failure
        // in environments where Redis is optional
        error_log('health: Redis extension not loaded');
    }
} catch (Throwable $e) {
    error_log('health: redis check failed: ' . $e->getMessage());
}

// --- Filesystem check ---
try {
    $uploadDir = getenv('AGVOTE_UPLOAD_DIR') ?: '/var/agvote/uploads';
    $testFile = $uploadDir . '/.health-check-' . getmypid();

    if (is_dir($uploadDir) && file_put_contents($testFile, 'ok') !== false) {
        $checks['filesystem'] = true;
        @unlink($testFile);
    } else {
        error_log('health: filesystem check failed: cannot write to ' . $uploadDir);
    }
} catch (Throwable $e) {
    error_log('health: filesystem check failed: ' . $e->getMessage());
}

// --- Aggregate status ---
$allOk = $checks['database'] && $checks['redis'] && $checks['filesystem'];
$status = $allOk ? 'ok' : 'degraded';
$httpCode = $allOk ? 200 : 503;

http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'checks' => $checks,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE);
