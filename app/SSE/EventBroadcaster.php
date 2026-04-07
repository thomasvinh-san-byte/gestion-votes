<?php

declare(strict_types=1);

namespace AgVote\SSE;

use AgVote\Core\Providers\RedisProvider;
use Redis;
use Throwable;

/**
 * EventBroadcaster - Service pour envoyer des evenements SSE (Server-Sent Events) via Redis.
 *
 * Uses Redis exclusively for event queuing and SSE fan-out. Redis is mandatory.
 */
class EventBroadcaster {
    private const QUEUE_KEY = 'sse:event_queue';
    private const MAX_QUEUE_SIZE = 1000;
    private const HEARTBEAT_KEY = 'sse:server:active';

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

    public static function documentAdded(string $meetingId, string $motionId, array $docData = []): void {
        self::toMeeting($meetingId, 'document.added', [
            'motion_id' => $motionId,
            'document' => $docData,
        ]);
    }

    public static function documentRemoved(string $meetingId, string $motionId, string $documentId): void {
        self::toMeeting($meetingId, 'document.removed', [
            'motion_id' => $motionId,
            'document_id' => $documentId,
        ]);
    }

    // ── Queue operations ────────────────────────────────────────────────

    /**
     * Check if push/SSE is enabled via PUSH_ENABLED env var.
     */
    public static function isPushEnabled(): bool {
        $val = getenv('PUSH_ENABLED');
        if ($val === false || $val === '') {
            return true; // enabled by default
        }
        return $val !== '0' && strtolower($val) !== 'false';
    }

    /**
     * Ajoute un evenement a la queue.
     */
    private static function queue(array $event): void {
        $event['queued_at'] = microtime(true);
        self::queueRedis($event);

        if (self::isPushEnabled()) {
            self::publishToSse($event);
        }
    }

    /**
     * Publish event to per-consumer SSE queues for real-time streaming.
     * Fans out to all registered consumers for the meeting so operator,
     * voters, and projection screens all receive every event.
     */
    private static function publishToSse(array $event): void {
        $meetingId = $event['meeting_id'] ?? null;
        if ($meetingId === null) {
            return;
        }

        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        try {
            $consumers = $redis->sMembers("sse:consumers:{$meetingId}");

            if (!empty($consumers)) {
                $encoded = json_encode($event);
                $pipe = $redis->multi(Redis::PIPELINE);
                foreach ($consumers as $consumerId) {
                    $queueKey = "sse:queue:{$meetingId}:{$consumerId}";
                    $pipe->rPush($queueKey, $encoded);
                    $pipe->expire($queueKey, 60);
                    $pipe->lTrim($queueKey, -100, -1);
                }
                $pipe->exec();
            }
        } finally {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
    }

    /**
     * Recupere et vide les evenements de la queue.
     * Utilise par le serveur SSE.
     */
    public static function dequeue(): array {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        try {
            $pipe = $redis->multi(Redis::PIPELINE);
            $pipe->lRange(self::QUEUE_KEY, 0, -1);
            $pipe->del(self::QUEUE_KEY);
            $results = $pipe->exec();

            $raw = $results[0] ?? [];
            if (!is_array($raw)) {
                return [];
            }

            return array_map(
                fn ($item) => is_string($item) ? (json_decode($item, true) ?? []) : $item,
                $raw,
            );
        } finally {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
    }

    /**
     * Verifie si le serveur SSE est actif via une cle Redis avec TTL.
     * La cle est ecrite par events.php a chaque iteration de la boucle.
     */
    public static function isServerRunning(): bool {
        try {
            $redis = RedisProvider::connection();
            return (bool) $redis->exists(self::HEARTBEAT_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    // ── Redis backend ───────────────────────────────────────────────────

    private static function queueRedis(array $event): void {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        try {
            $redis->rPush(self::QUEUE_KEY, json_encode($event));
            $redis->lTrim(self::QUEUE_KEY, -self::MAX_QUEUE_SIZE, -1);
        } finally {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
    }
}
