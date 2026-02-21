<?php

declare(strict_types=1);

namespace AgVote\WebSocket;

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
        } else {
            self::queueFile($event);
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

    private static function useRedis(): bool {
        if (!RedisProvider::isAvailable()) {
            return false;
        }
        try {
            RedisProvider::connection();
            return true;
        } catch (Throwable) {
            return false;
        }
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
            error_log('EventBroadcaster Redis push failed: ' . $e->getMessage());
            // Fallback to file
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
            error_log('EventBroadcaster Redis dequeue failed: ' . $e->getMessage());
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

            return $queue;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }
}
