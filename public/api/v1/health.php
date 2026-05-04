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
 * Each check now reports latency_ms — useful for orchestration probes that
 * track degradation BEFORE the binary up/down threshold trips. A check that
 * climbs from 5ms to 500ms is a leading indicator even though it still passes.
 *
 * Does NOT require authentication or CSRF.
 * Returns HTTP 200 + JSON on success, HTTP 503 on failure.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks = [
    'database' => ['ok' => false, 'latency_ms' => null],
    'redis' => ['ok' => false, 'latency_ms' => null],
    'filesystem' => ['ok' => false, 'latency_ms' => null],
];

/**
 * Returns elapsed milliseconds since a hrtime(true) baseline (rounded to 0.1ms).
 */
$elapsedMs = static function (int $started): float {
    return round((hrtime(true) - $started) / 1_000_000, 1);
};

// --- Database check ---
$t0 = hrtime(true);
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
    $checks['database']['ok'] = true;
} catch (Throwable $e) {
    \AgVote\Core\Logger::error('health: db check failed', ['exception' => $e->getMessage()]);
}
$checks['database']['latency_ms'] = $elapsedMs($t0);

// --- Redis check ---
$t0 = hrtime(true);
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
        $checks['redis']['ok'] = ($pong === true || $pong === '+PONG');
        $redis->close();
    } else {
        // Redis extension not loaded — mark as unavailable but not a failure
        // in environments where Redis is optional
        \AgVote\Core\Logger::warning('health: Redis extension not loaded');
    }
} catch (Throwable $e) {
    \AgVote\Core\Logger::error('health: redis check failed', ['exception' => $e->getMessage()]);
}
$checks['redis']['latency_ms'] = $elapsedMs($t0);

// --- Filesystem check ---
$t0 = hrtime(true);
try {
    $uploadDir = getenv('AGVOTE_UPLOAD_DIR') ?: '/var/agvote/uploads';
    $testFile = $uploadDir . '/.health-check-' . getmypid();

    if (is_dir($uploadDir) && file_put_contents($testFile, 'ok') !== false) {
        $checks['filesystem']['ok'] = true;
        @unlink($testFile);
    } else {
        \AgVote\Core\Logger::error('health: filesystem check failed — cannot write', ['upload_dir' => $uploadDir]);
    }
} catch (Throwable $e) {
    \AgVote\Core\Logger::error('health: filesystem check failed', ['exception' => $e->getMessage()]);
}
$checks['filesystem']['latency_ms'] = $elapsedMs($t0);

// --- Aggregate status ---
$allOk = $checks['database']['ok'] && $checks['redis']['ok'] && $checks['filesystem']['ok'];
$status = $allOk ? 'ok' : 'degraded';
$httpCode = $allOk ? 200 : 503;

http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'checks' => $checks,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE);
