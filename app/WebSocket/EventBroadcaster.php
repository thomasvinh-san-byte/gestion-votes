<?php
declare(strict_types=1);

namespace AgVote\WebSocket;

/**
 * EventBroadcaster - Service pour envoyer des evenements au serveur WebSocket.
 *
 * Utilise un fichier de queue pour communiquer avec le serveur WS
 * (pattern simple sans necessiter ZMQ ou Redis).
 */
class EventBroadcaster
{
    private const QUEUE_FILE = '/tmp/agvote-ws-queue.json';
    private const LOCK_FILE = '/tmp/agvote-ws-queue.lock';

    /**
     * Broadcast un evenement a un meeting.
     */
    public static function toMeeting(string $meetingId, string $eventType, array $data = []): void
    {
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
    public static function toTenant(string $tenantId, string $eventType, array $data = []): void
    {
        self::queue([
            'target' => 'tenant',
            'tenant_id' => $tenantId,
            'type' => $eventType,
            'data' => $data,
        ]);
    }

    /**
     * Events pre-definis pour les motions.
     */
    public static function motionOpened(string $meetingId, string $motionId, array $motionData = []): void
    {
        self::toMeeting($meetingId, 'motion.opened', [
            'motion_id' => $motionId,
            'motion' => $motionData,
        ]);
    }

    public static function motionClosed(string $meetingId, string $motionId, array $results = []): void
    {
        self::toMeeting($meetingId, 'motion.closed', [
            'motion_id' => $motionId,
            'results' => $results,
        ]);
    }

    public static function motionUpdated(string $meetingId, string $motionId, array $changes = []): void
    {
        self::toMeeting($meetingId, 'motion.updated', [
            'motion_id' => $motionId,
            'changes' => $changes,
        ]);
    }

    /**
     * Events pre-definis pour les votes.
     */
    public static function voteCast(string $meetingId, string $motionId, array $tally = []): void
    {
        self::toMeeting($meetingId, 'vote.cast', [
            'motion_id' => $motionId,
            'tally' => $tally,
        ]);
    }

    public static function voteUpdated(string $meetingId, string $motionId, array $tally = []): void
    {
        self::toMeeting($meetingId, 'vote.updated', [
            'motion_id' => $motionId,
            'tally' => $tally,
        ]);
    }

    /**
     * Events pre-definis pour la presence.
     */
    public static function attendanceUpdated(string $meetingId, array $stats = []): void
    {
        self::toMeeting($meetingId, 'attendance.updated', [
            'stats' => $stats,
        ]);
    }

    /**
     * Events pre-definis pour le quorum.
     */
    public static function quorumUpdated(string $meetingId, array $quorumData = []): void
    {
        self::toMeeting($meetingId, 'quorum.updated', [
            'quorum' => $quorumData,
        ]);
    }

    /**
     * Events pre-definis pour les changements de statut.
     */
    public static function meetingStatusChanged(string $meetingId, string $tenantId, string $newStatus, string $oldStatus = ''): void
    {
        self::toMeeting($meetingId, 'meeting.status_changed', [
            'meeting_id' => $meetingId,
            'new_status' => $newStatus,
            'old_status' => $oldStatus,
        ]);

        // Aussi notifier le tenant pour la liste des meetings
        self::toTenant($tenantId, 'meeting.status_changed', [
            'meeting_id' => $meetingId,
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Events pour la file de parole.
     */
    public static function speechQueueUpdated(string $meetingId, array $queue = []): void
    {
        self::toMeeting($meetingId, 'speech.queue_updated', [
            'queue' => $queue,
        ]);
    }

    /**
     * Ajoute un evenement a la queue.
     */
    private static function queue(array $event): void
    {
        $event['queued_at'] = microtime(true);

        $lockFile = fopen(self::LOCK_FILE, 'c');
        if (!$lockFile || !flock($lockFile, LOCK_EX)) {
            return; // Silently fail si lock impossible
        }

        try {
            $queue = [];
            if (file_exists(self::QUEUE_FILE)) {
                $content = file_get_contents(self::QUEUE_FILE);
                $queue = json_decode($content, true) ?? [];
            }

            $queue[] = $event;

            // Limiter la taille de la queue (garder les 1000 derniers)
            if (count($queue) > 1000) {
                $queue = array_slice($queue, -1000);
            }

            file_put_contents(self::QUEUE_FILE, json_encode($queue));
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    /**
     * Recupere et vide les evenements de la queue.
     * Utilise par le serveur WebSocket.
     */
    public static function dequeue(): array
    {
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

            // Vider la queue
            file_put_contents(self::QUEUE_FILE, '[]');

            return $queue;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    /**
     * Verifie si le serveur WebSocket est actif.
     */
    public static function isServerRunning(): bool
    {
        $pidFile = '/tmp/agvote-ws.pid';
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int) file_get_contents($pidFile);
        if ($pid <= 0) {
            return false;
        }

        // Verifier si le process existe
        return posix_kill($pid, 0);
    }
}
