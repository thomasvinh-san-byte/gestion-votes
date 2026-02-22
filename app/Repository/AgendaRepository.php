<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les points d'ordre du jour (agendas).
 */
class AgendaRepository extends AbstractRepository {
    /**
     * Liste les agendas d'une seance, tries par index.
     */
    public function listForMeeting(string $meetingId, string $tenantId = ''): array {
        $sql = 'SELECT id, meeting_id, idx, title, description, is_approved, created_at
                FROM agendas
                WHERE meeting_id = :meeting_id';
        $params = [':meeting_id' => $meetingId];
        if ($tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' ORDER BY idx ASC';
        return $this->selectAll($sql, $params);
    }

    /**
     * Liste les agendas avec colonnes renommees (pour agendas_for_meeting).
     */
    public function listForMeetingCompact(string $meetingId, string $tenantId = ''): array {
        $sql = 'SELECT id AS agenda_id, title AS agenda_title, idx AS agenda_idx
                FROM agendas
                WHERE meeting_id = :meeting_id';
        $params = [':meeting_id' => $meetingId];
        if ($tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' ORDER BY idx ASC';
        return $this->selectAll($sql, $params);
    }

    /**
     * Retourne le prochain index pour un agenda dans une seance.
     */
    public function nextIdx(string $meetingId): int {
        $idx = $this->scalar(
            'SELECT COALESCE(MAX(idx), 0) + 1 FROM agendas WHERE meeting_id = :meeting_id',
            [':meeting_id' => $meetingId],
        );
        return $idx !== null ? (int) $idx : 1;
    }

    /**
     * Cree un point d'ordre du jour.
     */
    public function create(string $id, string $tenantId, string $meetingId, int $idx, string $title): void {
        $this->execute(
            'INSERT INTO agendas (id, tenant_id, meeting_id, idx, title, description, is_approved, created_at)
             VALUES (:id, :tenant_id, :meeting_id, :idx, :title, NULL, false, NOW())',
            [
                ':id' => $id,
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':idx' => $idx,
                ':title' => $title,
            ],
        );
    }
}
