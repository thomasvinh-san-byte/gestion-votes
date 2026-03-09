<?php

declare(strict_types=1);

namespace AgVote\WebSocket;

use AgVote\Core\Logger;
use AgVote\Core\Providers\RedisProvider;
use Redis;
use Throwable;

/**
 * EventBroadcaster - Service pour envoyer des evenements au serveur WebSocket.
 *
 * Uses Redis LPUSH/LRANGE+DEL when available, falls back to file-based queue.
 */
class EventBroadcaster {
    private const QUEUE_KEY = 'ws:event_queue';
    private const QUEUE_FILE = '/tmp/agvote-ws-queue.json';
    private const LOCK_FILE = '/tmp/agvote-ws-queue.lock';
    private const MAX_QUEUE_SIZE = 1000;

    /**
     * Broadcast un evenement a un meeting.
     */
    public static function toMeeting(string $meetingId, string $eventType, array $data = []): void {
        self::queue([
            'target' => 'meeting',
            'meeting_id' => $meetingId,
            'type' => $eventType,
            'data' => $data,
        ]);
    }

    /**
     * Broadcast un evenement a un tenant.
     */
    public static function toTenant(string $tenantId, string $eventType, array $data = []): void {
        self::queue([
            'target' => 'tenant',
            'tenant_id' => $tenantId,
            'type' => $eventType,
            'data' => $data,
        ]);
    }

    // ── Pre-defined events ──────────────────────────────────────────────

    public static function motionOpened(string $meetingId, string $motionId, array $motionData = []): void {
        self::toMeeting($meetingId, 'motion.opened', [
            'motion_id' => $motionId,
            'motion' => $motionData,
        ]);
    }

    public static function motionClosed(string $meetingId, string $motionId, array $results = []): void {
        self::toMeeting($meetingId, 'motion.closed', [
            'motion_id' => $motionId,
            'results' => $results,
        ]);
    }

    public static function motionUpdated(string $meetingId, string $motionId, array $changes = []): void {
        self::toMeeting($meetingId, 'motion.updated', [
            'motion_id' => $motionId,
            'changes' => $changes,
        ]);
    }

    public static function voteCast(string $meetingId, string $motionId, array $tally = []): void {
        self::toMeeting($meetingId, 'vote.cast', [
            'motion_id' => $motionId,
            'tally' => $tally,
        ]);
    }

    public static function voteUpdated(string $meetingId, string $motionId, array $tally = []): void {
        self::toMeeting($meetingId, 'vote.updated', [
            'motion_id' => $motionId,
            'tally' => $tally,
        ]);
    }

    public static function attendanceUpdated(string $meetingId, array $stats = []): void {
        self::toMeeting($meetingId, 'attendance.updated', [
            'stats' => $stats,
        ]);
    }

    public static function quorumUpdated(string $meetingId, array $quorumData = []): void {
        self::toMeeting($meetingId, 'quorum.updated', [
            'quorum' => $quorumData,
        ]);
    }

    public static function meetingStatusChanged(string $meetingId, string $tenantId, string $newStatus, string $oldStatus = ''): void {
        self::toMeeting($meetingId, 'meeting.status_changed', [
            'meeting_id' => $meetingId,
            'new_status' => $newStatus,
            'old_status' => $oldStatus,
        ]);

        self::toTenant($tenantId, 'meeting.status_changed', [
            'meeting_id' => $meetingId,
            'new_status' => $newStatus,
        ]);
    }

    public static function speechQueueUpdated(string $meetingId, array $queue = []): void {
        self::toMeeting($meetingId, 'speech.queue_updated', [
            'queue' => $queue,
        ]);
    }

    // ── Queue operations ────────────────────────────────────────────────

    /**
     * Ajoute un evenement a la queue.
     */
    private static function queue(array $event): void {
        $event['queued_at'] = microtime(true);

        if (self::useRedis()) {
            self::queueRedis($event);
            self::publishToSse($event);
        } else {
            self::queueFile($event);
        }
    }

    /**
     * Publish event to per-meeting SSE list for real-time streaming.
     * SSE clients poll these lists via /api/v1/events.php.
     */
    private static function publishToSse(array $event): void {
        $meetingId = $event['meeting_id'] ?? null;
        if ($meetingId === null) {
            return;
        }

        try {
            $redis = RedisProvider::connection();
            $sseKey = 'sse:events:' . $meetingId;
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
            $redis->rPush($sseKey, json_encode($event));
            // Auto-expire after 60s to prevent memory leaks for inactive meetings
            $redis->expire($sseKey, 60);
            // Trim to keep max 100 events per meeting
            $redis->lTrim($sseKey, -100, -1);
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
        } catch (Throwable $e) {
            // Non-critical: SSE clients will miss this event but polling still works
        }
    }

    /**
     * Recupere et vide les evenements de la queue.
     * Utilise par le serveur WebSocket.
     */
    public static function dequeue(): array {
        if (self::useRedis()) {
            return self::dequeueRedis();
        }
        return self::dequeueFile();
    }

    /**
     * Verifie si le serveur WebSocket est actif.
     */
    public static function isServerRunning(): bool {
        $pidFile = '/tmp/agvote-ws.pid';
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int) file_get_contents($pidFile);
        if ($pid <= 0) {
            return false;
        }

        return posix_kill($pid, 0);
    }

    // ── Redis backend ───────────────────────────────────────────────────

    /** Tracks whether we have already logged a Redis fallback warning this request. */
    private static bool $redisFallbackLogged = false;

    private static function useRedis(): bool {
        if (!RedisProvider::isAvailable()) {
            self::logRedisFallback('phpredis extension not loaded');
            return false;
        }
        try {
            RedisProvider::connection();
            return true;
        } catch (Throwable $e) {
            self::logRedisFallback($e->getMessage());
            return false;
        }
    }

    /**
     * Log a warning once per request when falling back to file-based queue.
     */
    private static function logRedisFallback(string $reason): void {
        if (self::$redisFallbackLogged) {
            return;
        }
        self::$redisFallbackLogged = true;
        Logger::warning('EventBroadcaster falling back to file queue', [
            'reason' => $reason,
            'queue_file' => self::QUEUE_FILE,
        ]);
    }

    private static function queueRedis(array $event): void {
        try {
            $redis = RedisProvider::connection();
            // Temporarily disable JSON serializer for raw string push
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
            $redis->rPush(self::QUEUE_KEY, json_encode($event));

            // Trim to max size (keep most recent)
            $redis->lTrim(self::QUEUE_KEY, -self::MAX_QUEUE_SIZE, -1);
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        } catch (Throwable $e) {
            Logger::warning('EventBroadcaster Redis push failed, falling back to file', [
                'error' => $e->getMessage(),
            ]);
            self::queueFile($event);
        }
    }

    private static function dequeueRedis(): array {
        try {
            $redis = RedisProvider::connection();
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

            // Atomic: get all + delete
            $pipe = $redis->multi(Redis::PIPELINE);
            $pipe->lRange(self::QUEUE_KEY, 0, -1);
            $pipe->del(self::QUEUE_KEY);
            $results = $pipe->exec();

            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

            $raw = $results[0] ?? [];
            if (!is_array($raw)) {
                return [];
            }

            return array_map(
                fn ($item) => is_string($item) ? (json_decode($item, true) ?? []) : $item,
                $raw,
            );
        } catch (Throwable $e) {
            Logger::warning('EventBroadcaster Redis dequeue failed', ['error' => $e->getMessage()]);
            return self::dequeueFile();
        }
    }

    // ── File backend (fallback) ─────────────────────────────────────────

    private static function queueFile(array $event): void {
        $lockFile = fopen(self::LOCK_FILE, 'c');
        if (!$lockFile || !flock($lockFile, LOCK_EX)) {
            return;
        }

        try {
            $queue = [];
            if (file_exists(self::QUEUE_FILE)) {
                $content = file_get_contents(self::QUEUE_FILE);
                $queue = json_decode($content, true) ?? [];
            }

            $queue[] = $event;

            if (count($queue) > self::MAX_QUEUE_SIZE) {
                $queue = array_slice($queue, -self::MAX_QUEUE_SIZE);
            }

            file_put_contents(self::QUEUE_FILE, json_encode($queue));
            chmod(self::QUEUE_FILE, 0600);
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    private static function dequeueFile(): array {
        $lockFile = fopen(self::LOCK_FILE, 'c');
        if (!$lockFile || !flock($lockFile, LOCK_EX)) {
            return [];
        }

        try {
            if (!file_exists(self::QUEUE_FILE)) {
                return [];
            }

            $content = file_get_contents(self::QUEUE_FILE);
            $queue = json_decode($content, true) ?? [];

            file_put_contents(self::QUEUE_FILE, '[]');
            chmod(self::QUEUE_FILE, 0600);

            return $queue;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }
}
