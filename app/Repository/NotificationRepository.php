<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les notifications de seance (meeting_notifications)
 * et le cache d'etat de validation (meeting_validation_state).
 */
class NotificationRepository extends AbstractRepository {
    // =========================================================================
    // VALIDATION STATE
    // =========================================================================

    /**
     * Trouve l'etat de validation precedent pour une seance.
     */
    public function findValidationState(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT ready, codes FROM meeting_validation_state WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Upsert l'etat de validation d'une seance.
     */
    public function upsertValidationState(string $meetingId, string $tenantId, bool $ready, string $codesJson): void {
        $this->execute(
            'INSERT INTO meeting_validation_state (meeting_id, tenant_id, ready, codes)
             VALUES (:mid, :tid, :ready, :codes::jsonb)
             ON CONFLICT (meeting_id) DO UPDATE SET ready = EXCLUDED.ready, codes = EXCLUDED.codes, updated_at = now()',
            [':mid' => $meetingId, ':tid' => $tenantId, ':ready' => $ready, ':codes' => $codesJson],
        );
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * Dedoublonnage: compte les notifications recentes avec meme code+message.
     */
    public function countRecentDuplicates(string $meetingId, string $code, string $message, string $tenantId): int {
        return (int) ($this->scalar(
            "SELECT count(*) FROM meeting_notifications
             WHERE meeting_id = :mid AND code = :code AND message = :msg AND tenant_id = :tid
               AND created_at > (now() - interval '10 seconds')",
            [':mid' => $meetingId, ':code' => $code, ':msg' => $message, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Insere une notification.
     */
    public function insert(
        string $tenantId,
        string $meetingId,
        string $severity,
        string $code,
        string $message,
        string $audienceLiteral,
        string $dataJson,
    ): void {
        $this->execute(
            'INSERT INTO meeting_notifications (tenant_id, meeting_id, severity, code, message, audience, data)
             VALUES (:tid, :mid, :sev, :code, :msg, :aud::text[], :data::jsonb)',
            [
                ':tid' => $tenantId, ':mid' => $meetingId, ':sev' => $severity,
                ':code' => $code, ':msg' => $message, ':aud' => $audienceLiteral, ':data' => $dataJson,
            ],
        );
    }

    /**
     * Liste les notifications par audience (depuis un ID, ordre ASC).
     */
    public function listSinceId(string $meetingId, int $sinceId, int $limit, string $audience = '', string $tenantId = ''): array {
        $limit = max(1, min(100, $limit));
        if ($audience === '' || $audience === 'all') {
            $params = [':mid' => $meetingId, ':since' => $sinceId, ':lim' => $limit];
            $tenantClause = '';
            if ($tenantId !== '') {
                $tenantClause = ' AND tenant_id = :tid';
                $params[':tid'] = $tenantId;
            }
            return $this->selectAll(
                "SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = :mid AND id > :since{$tenantClause}
                 ORDER BY id ASC LIMIT :lim",
                $params,
            );
        }
        $params = [':mid' => $meetingId, ':since' => $sinceId, ':aud' => $audience, ':lim' => $limit];
        $tenantClause = '';
        if ($tenantId !== '') {
            $tenantClause = ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectAll(
            "SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = :mid AND id > :since
               AND (audience @> ARRAY[:aud]::text[]){$tenantClause}
             ORDER BY id ASC LIMIT :lim",
            $params,
        );
    }

    /**
     * Dernieres notifications (ordre DESC, pour init UI).
     */
    public function listRecent(string $meetingId, int $limit, string $audience = '', string $tenantId = ''): array {
        $limit = max(1, min(200, $limit));
        if ($audience === '' || $audience === 'all') {
            $params = [':mid' => $meetingId, ':lim' => $limit];
            $tenantClause = '';
            if ($tenantId !== '') {
                $tenantClause = ' AND tenant_id = :tid';
                $params[':tid'] = $tenantId;
            }
            return $this->selectAll(
                "SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = :mid{$tenantClause}
                 ORDER BY id DESC LIMIT :lim",
                $params,
            );
        }
        $params = [':mid' => $meetingId, ':aud' => $audience, ':lim' => $limit];
        $tenantClause = '';
        if ($tenantId !== '') {
            $tenantClause = ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectAll(
            "SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = :mid
               AND (audience @> ARRAY[:aud]::text[]){$tenantClause}
             ORDER BY id DESC LIMIT :lim",
            $params,
        );
    }

    /**
     * Marque une notification comme lue.
     */
    public function markRead(string $meetingId, int $id, string $tenantId): void {
        if ($id <= 0) {
            return;
        }
        $this->execute(
            'UPDATE meeting_notifications SET read_at = now()
             WHERE meeting_id = :mid AND id = :id AND tenant_id = :tid AND read_at IS NULL',
            [':mid' => $meetingId, ':id' => $id, ':tid' => $tenantId],
        );
    }

    /**
     * Marque toutes les notifications comme lues (par audience).
     */
    public function markAllRead(string $meetingId, string $audience = '', string $tenantId): void {
        if ($audience === '' || $audience === 'all') {
            $this->execute(
                'UPDATE meeting_notifications SET read_at = now()
                 WHERE meeting_id = :mid AND tenant_id = :tid AND read_at IS NULL',
                [':mid' => $meetingId, ':tid' => $tenantId],
            );
            return;
        }
        $this->execute(
            'UPDATE meeting_notifications SET read_at = now()
             WHERE meeting_id = :mid AND tenant_id = :tid AND read_at IS NULL AND (audience @> ARRAY[:aud]::text[])',
            [':mid' => $meetingId, ':tid' => $tenantId, ':aud' => $audience],
        );
    }

    /**
     * Supprime les notifications (par audience).
     */
    public function clear(string $meetingId, string $audience = '', string $tenantId): void {
        if ($audience === '' || $audience === 'all') {
            $this->execute('DELETE FROM meeting_notifications WHERE meeting_id = :mid AND tenant_id = :tid',
                [':mid' => $meetingId, ':tid' => $tenantId]);
            return;
        }
        $this->execute(
            'DELETE FROM meeting_notifications WHERE meeting_id = :mid AND tenant_id = :tid AND (audience @> ARRAY[:aud]::text[])',
            [':mid' => $meetingId, ':tid' => $tenantId, ':aud' => $audience],
        );
    }
}
