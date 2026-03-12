<?php

/**
 * Server-Sent Events (SSE) endpoint for real-time updates.
 *
 * Replaces polling by streaming events from Redis (EventBroadcaster queue).
 * Holds the PHP-FPM worker for up to 30s, then the client auto-reconnects
 * (built into the EventSource API).
 *
 * Usage:
 *   const es = new EventSource('/api/v1/events.php?meeting_id=xxx');
 *   es.addEventListener('vote.cast', (e) => { ... });
 *   es.addEventListener('motion.opened', (e) => { ... });
 *
 * Authentication: session-based (same as other API endpoints).
 * Query params:
 *   - meeting_id (required): filter events for this meeting
 */

declare(strict_types=1);

// Boot app for auth + Redis access
require_once __DIR__ . '/../../../app/bootstrap.php';

use AgVote\Core\Providers\RedisProvider;
use AgVote\WebSocket\EventBroadcaster;

// ── Auth check (reuse session middleware logic) ──────────────────────────
$authEnabled = getenv('APP_AUTH_ENABLED');
if ($authEnabled !== '0' && strtolower((string) $authEnabled) !== 'false') {
    \AgVote\Core\Security\SessionHelper::start();
    if (empty($_SESSION['auth_user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'authentication_required']);
        exit;
    }
    // Enforce session timeout (same as AuthMiddleware::SESSION_TIMEOUT)
    $lastActivity = $_SESSION['auth_last_activity'] ?? 0;
    if ($lastActivity > 0 && (time() - $lastActivity) > 1800) {
        \AgVote\Core\Security\SessionHelper::destroy();
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'session_expired']);
        exit;
    }
    $_SESSION['auth_last_activity'] = time();
}

// Check if push/SSE is enabled
if (!EventBroadcaster::isPushEnabled()) {
    http_response_code(204);
    exit;
}

$meetingId = $_GET['meeting_id'] ?? '';
if ($meetingId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $meetingId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'invalid_meeting_id']);
    exit;
}

// ── SSE headers ──────────────────────────────────────────────────────────
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}

// Extend execution time for SSE
set_time_limit(35);
ignore_user_abort(false);

// ── Redis Pub/Sub channel ────────────────────────────────────────────────
// EventBroadcaster uses LPUSH to a queue key. For SSE, we use a separate
// Redis SUBSCRIBE channel so multiple SSE clients can receive the same events.
// The broadcaster also publishes to a channel (we add this capability).

$channelKey = "sse:meeting:{$meetingId}";
$fallbackKey = 'ws:event_queue'; // Original queue key for fallback

// Send initial connection event
sendEvent('connected', ['meeting_id' => $meetingId, 'server_time' => date('c')]);

$startTime = time();
$maxDuration = 30; // seconds
$lastEventId = 0;

// ── Main event loop ─────────────────────────────────────────────────────
// Strategy: Poll Redis channel every 1s for new events.
// This is a pragmatic approach for PHP-FPM (no true async pub/sub).

while ((time() - $startTime) < $maxDuration) {
    if (connection_aborted()) {
        break;
    }

    $events = pollEvents($meetingId);

    foreach ($events as $event) {
        $lastEventId++;
        sendEvent(
            $event['type'] ?? 'message',
            $event['data'] ?? $event,
            (string) $lastEventId,
        );
    }

    // Send keepalive comment every iteration to detect disconnects
    echo ": keepalive\n\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    // Sleep 1s between polls (balance between latency and CPU)
    sleep(1);
}

// Close gracefully
sendEvent('reconnect', ['reason' => 'timeout', 'retry' => 1000]);

// ── Helper functions ─────────────────────────────────────────────────────

function sendEvent(string $type, array $data, string $id = ''): void {
    if ($id !== '') {
        echo "id: {$id}\n";
    }
    echo "event: {$type}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Poll for events targeting this meeting.
 *
 * Uses Redis per-meeting SSE list when available,
 * falls back to file-based queue (EventBroadcaster) otherwise.
 */
function pollEvents(string $meetingId): array {
    // Try Redis first
    if (RedisProvider::isAvailable()) {
        try {
            $redis = RedisProvider::connection();
            $sseKey = "sse:events:{$meetingId}";

            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

            $pipe = $redis->multi(Redis::PIPELINE);
            $pipe->lRange($sseKey, 0, -1);
            $pipe->del($sseKey);
            $results = $pipe->exec();

            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

            $raw = $results[0] ?? [];
            if (is_array($raw) && count($raw) > 0) {
                $events = [];
                foreach ($raw as $item) {
                    $decoded = is_string($item) ? json_decode($item, true) : $item;
                    if (is_array($decoded)) {
                        $events[] = $decoded;
                    }
                }
                return $events;
            }

            return [];
        } catch (Throwable $e) {
            // Fall through to file-based polling
        }
    }

    // File-based fallback (works without Redis — ideal for demo / Render)
    return EventBroadcaster::dequeueSseFile($meetingId);
}
