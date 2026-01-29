<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * SpeechService — gestion de la parole (main levée) inspirée du prototype TSX.
 *
 * Table attendue: speech_requests
 *  - id (uuid), tenant_id, meeting_id, member_id
 *  - status: waiting|speaking|finished|cancelled
 *  - created_at, updated_at
 */
final class SpeechService
{
    private static function ensureSchema(): void
    {
        // Best-effort: si la migration n'a pas été jouée, on crée le minimum.
        db_execute("CREATE TABLE IF NOT EXISTS speech_requests (
            id uuid PRIMARY KEY,
            tenant_id uuid NOT NULL,
            meeting_id uuid NOT NULL,
            member_id uuid NOT NULL,
            status text NOT NULL CHECK (status IN ('waiting','speaking','finished','cancelled')),
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now()
        )");
        db_execute("CREATE INDEX IF NOT EXISTS idx_speech_requests_meeting_status ON speech_requests (meeting_id, status, created_at)");
        db_execute("CREATE INDEX IF NOT EXISTS idx_speech_requests_member ON speech_requests (meeting_id, member_id, updated_at DESC)");
    }

    /** @return array{speaker: ?array<string,mixed>, queue: array<int,array<string,mixed>>} */
    public static function getQueue(string $meetingId): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        $tenantId = (string)$meeting['tenant_id'];

        $speaker = db_one(
            "SELECT sr.*, m.first_name, m.last_name
             FROM speech_requests sr
             JOIN members m ON m.id = sr.member_id
             WHERE sr.meeting_id = :mid AND sr.tenant_id = :tid AND sr.status = 'speaking'
             ORDER BY sr.updated_at DESC
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );

        $queue = db_all(
            "SELECT sr.*, m.first_name, m.last_name
             FROM speech_requests sr
             JOIN members m ON m.id = sr.member_id
             WHERE sr.meeting_id = :mid AND sr.tenant_id = :tid AND sr.status = 'waiting'
             ORDER BY sr.created_at ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );

        return ['speaker' => $speaker ?: null, 'queue' => $queue];
    }

    /** @return array{status: string, request_id: ?string} */
    public static function getMyStatus(string $meetingId, string $memberId): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        $tenantId = (string)$meeting['tenant_id'];

        $row = db_one(
            "SELECT id, status
             FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND member_id = :mem
               AND status IN ('waiting','speaking')
             ORDER BY updated_at DESC
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId, ':mem' => $memberId]
        );

        if (!$row) return ['status' => 'none', 'request_id' => null];

        return ['status' => (string)$row['status'], 'request_id' => (string)$row['id']];
    }

    /**
     * Toggle request: si déjà waiting -> cancel; sinon -> créer waiting.
     * @return array{status: string, request_id: ?string}
     */
    public static function toggleRequest(string $meetingId, string $memberId): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) throw new RuntimeException('Séance introuvable');
        $tenantId = (string)$meeting['tenant_id'];

        $existing = db_one(
            "SELECT id, status FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND member_id = :mem
               AND status IN ('waiting','speaking')
             ORDER BY updated_at DESC
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId, ':mem' => $memberId]
        );

        if ($existing && (string)$existing['status'] === 'waiting') {
            db_execute(
                "UPDATE speech_requests SET status='cancelled', updated_at=now()
                 WHERE id = :id AND tenant_id=:tid",
                [':id' => (string)$existing['id'], ':tid' => $tenantId]
            );
            audit_log('speech_cancelled', 'meeting', $meetingId, ['member_id' => $memberId]);
            return ['status' => 'none', 'request_id' => null];
        }

        if ($existing && (string)$existing['status'] === 'speaking') {
            // Un orateur actif ne peut pas relancer -> il baisse la main (fin)
            db_execute(
                "UPDATE speech_requests SET status='finished', updated_at=now()
                 WHERE id = :id AND tenant_id=:tid",
                [':id' => (string)$existing['id'], ':tid' => $tenantId]
            );
            audit_log('speech_finished_self', 'meeting', $meetingId, ['member_id' => $memberId]);
            return ['status' => 'none', 'request_id' => null];
        }

        $id = uuid_v4();
        db_execute(
            "INSERT INTO speech_requests (id, tenant_id, meeting_id, member_id, status)
             VALUES (:id, :tid, :mid, :mem, 'waiting')",
            [':id' => $id, ':tid' => $tenantId, ':mid' => $meetingId, ':mem' => $memberId]
        );
        audit_log('speech_requested', 'meeting', $meetingId, ['member_id' => $memberId]);
        return ['status' => 'waiting', 'request_id' => $id];
    }

    /** Donne la parole: soit au membre fourni, soit au prochain de la file. */
    public static function grant(string $meetingId, ?string $memberId = null): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) throw new RuntimeException('Séance introuvable');
        $tenantId = (string)$meeting['tenant_id'];

        // Terminer l'orateur courant s'il existe
        db_execute(
            "UPDATE speech_requests SET status='finished', updated_at=now()
             WHERE meeting_id = :mid AND tenant_id=:tid AND status='speaking'",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );

        if ($memberId) {
            // Prendre la demande waiting la plus récente de ce membre, sinon créer speaking direct
            $req = db_one(
                "SELECT id FROM speech_requests
                 WHERE meeting_id=:mid AND tenant_id=:tid AND member_id=:mem AND status='waiting'
                 ORDER BY created_at ASC LIMIT 1",
                [':mid' => $meetingId, ':tid' => $tenantId, ':mem' => $memberId]
            );

            if ($req) {
                db_execute(
                    "UPDATE speech_requests SET status='speaking', updated_at=now()
                     WHERE id=:id AND tenant_id=:tid",
                    [':id' => (string)$req['id'], ':tid' => $tenantId]
                );
                audit_log('speech_granted', 'meeting', $meetingId, ['member_id' => $memberId, 'request_id' => (string)$req['id']]);
                return self::getQueue($meetingId);
            }

            // speaking direct
            $id = uuid_v4();
            db_execute(
                "INSERT INTO speech_requests (id, tenant_id, meeting_id, member_id, status)
                 VALUES (:id,:tid,:mid,:mem,'speaking')",
                [':id' => $id, ':tid' => $tenantId, ':mid' => $meetingId, ':mem' => $memberId]
            );
            audit_log('speech_granted_direct', 'meeting', $meetingId, ['member_id' => $memberId, 'request_id' => $id]);
            return self::getQueue($meetingId);
        }

        // Pick next waiting
        $next = db_one(
            "SELECT id, member_id FROM speech_requests
             WHERE meeting_id=:mid AND tenant_id=:tid AND status='waiting'
             ORDER BY created_at ASC LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );

        if ($next) {
            db_execute(
                "UPDATE speech_requests SET status='speaking', updated_at=now()
                 WHERE id=:id AND tenant_id=:tid",
                [':id' => (string)$next['id'], ':tid' => $tenantId]
            );
            audit_log('speech_granted_next', 'meeting', $meetingId, ['member_id' => (string)$next['member_id'], 'request_id' => (string)$next['id']]);
        }

        return self::getQueue($meetingId);
    }

    public static function endCurrent(string $meetingId): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) throw new RuntimeException('Séance introuvable');
        $tenantId = (string)$meeting['tenant_id'];

        db_execute(
            "UPDATE speech_requests SET status='finished', updated_at=now()
             WHERE meeting_id=:mid AND tenant_id=:tid AND status='speaking'",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
        audit_log('speech_ended', 'meeting', $meetingId, []);
        return self::getQueue($meetingId);
    }

    public static function clearHistory(string $meetingId): array
    {
        self::ensureSchema();

        $meeting = db_one("SELECT id, tenant_id FROM meetings WHERE id = :id", [':id' => $meetingId]);
        if (!$meeting) throw new RuntimeException('Séance introuvable');
        $tenantId = (string)$meeting['tenant_id'];

        db_execute(
            "DELETE FROM speech_requests
             WHERE meeting_id=:mid AND tenant_id=:tid AND status IN ('finished','cancelled')",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
        audit_log('speech_cleared', 'meeting', $meetingId, []);
        return self::getQueue($meetingId);
    }
}
