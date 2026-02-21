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
    public function findValidationState(string $meetingId): ?array {
        return $this->selectOne(
            'SELECT ready, codes FROM meeting_validation_state WHERE meeting_id = ?',
            [$meetingId],
        );
    }

    /**
     * Upsert l'etat de validation d'une seance.
     */
    public function upsertValidationState(string $meetingId, string $tenantId, bool $ready, string $codesJson): void {
        $this->execute(
            'INSERT INTO meeting_validation_state (meeting_id, tenant_id, ready, codes)
             VALUES (?, ?, ?, ?::jsonb)
             ON CONFLICT (meeting_id) DO UPDATE SET ready = EXCLUDED.ready, codes = EXCLUDED.codes, updated_at = now()',
            [$meetingId, $tenantId, $ready, $codesJson],
        );
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * Dedoublonnage: compte les notifications recentes avec meme code+message.
     */
    public function countRecentDuplicates(string $meetingId, string $code, string $message): int {
        return (int) ($this->scalar(
            "SELECT count(*) FROM meeting_notifications
             WHERE meeting_id = ? AND code = ? AND message = ?
               AND created_at > (now() - interval '10 seconds')",
            [$meetingId, $code, $message],
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
             VALUES (?, ?, ?, ?, ?, ?::text[], ?::jsonb)',
            [$tenantId, $meetingId, $severity, $code, $message, $audienceLiteral, $dataJson],
        );
    }

    /**
     * Liste les notifications par audience (depuis un ID, ordre ASC).
     */
    public function listSinceId(string $meetingId, int $sinceId, int $limit, string $audience = ''): array {
        $limit = max(1, min(100, $limit));
        if ($audience === '' || $audience === 'all') {
            return $this->selectAll(
                'SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = ? AND id > ?
                 ORDER BY id ASC
                 LIMIT ' . $limit,
                [$meetingId, $sinceId],
            );
        }
        return $this->selectAll(
            'SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = ? AND id > ?
               AND (audience @> ARRAY[?]::text[])
             ORDER BY id ASC
             LIMIT ' . $limit,
            [$meetingId, $sinceId, $audience],
        );
    }

    /**
     * Dernieres notifications (ordre DESC, pour init UI).
     */
    public function listRecent(string $meetingId, int $limit, string $audience = ''): array {
        $limit = max(1, min(200, $limit));
        if ($audience === '' || $audience === 'all') {
            return $this->selectAll(
                'SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = ?
                 ORDER BY id DESC
                 LIMIT ' . $limit,
                [$meetingId],
            );
        }
        return $this->selectAll(
            'SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = ?
               AND (audience @> ARRAY[?]::text[])
             ORDER BY id DESC
             LIMIT ' . $limit,
            [$meetingId, $audience],
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
             WHERE meeting_id = ? AND id = ? AND tenant_id = ? AND read_at IS NULL',
            [$meetingId, $id, $tenantId],
        );
    }

    /**
     * Marque toutes les notifications comme lues (par audience).
     */
    public function markAllRead(string $meetingId, string $audience = '', string $tenantId): void {
        $params = [$meetingId, $tenantId];

        if ($audience === '' || $audience === 'all') {
            $this->execute(
                'UPDATE meeting_notifications SET read_at = now()
                 WHERE meeting_id = ? AND tenant_id = ? AND read_at IS NULL',
                $params,
            );
            return;
        }
        $params[] = $audience;
        $this->execute(
            'UPDATE meeting_notifications SET read_at = now()
             WHERE meeting_id = ? AND tenant_id = ? AND read_at IS NULL AND (audience @> ARRAY[?]::text[])',
            $params,
        );
    }

    /**
     * Supprime les notifications (par audience).
     */
    public function clear(string $meetingId, string $audience = '', string $tenantId): void {
        $params = [$meetingId, $tenantId];

        if ($audience === '' || $audience === 'all') {
            $this->execute('DELETE FROM meeting_notifications WHERE meeting_id = ? AND tenant_id = ?', $params);
            return;
        }
        $params[] = $audience;
        $this->execute(
            'DELETE FROM meeting_notifications WHERE meeting_id = ? AND tenant_id = ? AND (audience @> ARRAY[?]::text[])',
            $params,
        );
    }
}
