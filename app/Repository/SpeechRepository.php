<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les demandes de parole (speech_requests).
 */
class SpeechRepository extends AbstractRepository
{
    /**
     * Creation best-effort de la table speech_requests si absente.
     */
    public function ensureSchema(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS speech_requests (
            id uuid PRIMARY KEY,
            tenant_id uuid NOT NULL,
            meeting_id uuid NOT NULL,
            member_id uuid NOT NULL,
            status text NOT NULL CHECK (status IN ('waiting','speaking','finished','cancelled')),
            created_at timestamptz NOT NULL DEFAULT now(),
            updated_at timestamptz NOT NULL DEFAULT now()
        )");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_speech_requests_meeting_status ON speech_requests (meeting_id, status, created_at)");
        $this->execute("CREATE INDEX IF NOT EXISTS idx_speech_requests_member ON speech_requests (meeting_id, member_id, updated_at DESC)");
    }

    /**
     * Trouve l'orateur courant (status = speaking) pour une seance.
     */
    public function findCurrentSpeaker(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT sr.*, m.full_name
             FROM speech_requests sr
             JOIN members m ON m.id = sr.member_id
             WHERE sr.meeting_id = :mid AND sr.tenant_id = :tid AND sr.status = 'speaking'
             ORDER BY sr.updated_at DESC
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste la file d'attente (status = waiting) pour une seance.
     */
    public function listWaiting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT sr.*, m.full_name
             FROM speech_requests sr
             JOIN members m ON m.id = sr.member_id
             WHERE sr.meeting_id = :mid AND sr.tenant_id = :tid AND sr.status = 'waiting'
             ORDER BY sr.created_at ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Trouve la demande active (waiting/speaking) d'un membre.
     */
    public function findActive(string $meetingId, string $tenantId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT id, status
             FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND member_id = :mem
               AND status IN ('waiting','speaking')
             ORDER BY updated_at DESC
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId, ':mem' => $memberId]
        );
    }

    /**
     * Trouve une demande en attente pour un membre specifique.
     */
    public function findWaitingForMember(string $meetingId, string $tenantId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT id FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND member_id = :mem AND status = 'waiting'
             ORDER BY created_at ASC LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId, ':mem' => $memberId]
        );
    }

    /**
     * Trouve le prochain en file d'attente.
     */
    public function findNextWaiting(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, member_id FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND status = 'waiting'
             ORDER BY created_at ASC LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Met a jour le statut d'une demande.
     */
    public function updateStatus(string $id, string $tenantId, string $status): void
    {
        $this->execute(
            "UPDATE speech_requests SET status = :status, updated_at = now()
             WHERE id = :id AND tenant_id = :tid",
            [':status' => $status, ':id' => $id, ':tid' => $tenantId]
        );
    }

    /**
     * Termine tous les orateurs courants pour une seance.
     */
    public function finishAllSpeaking(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "UPDATE speech_requests SET status = 'finished', updated_at = now()
             WHERE meeting_id = :mid AND tenant_id = :tid AND status = 'speaking'",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Insere une nouvelle demande de parole.
     */
    public function insert(string $id, string $tenantId, string $meetingId, string $memberId, string $status): void
    {
        $this->execute(
            "INSERT INTO speech_requests (id, tenant_id, meeting_id, member_id, status)
             VALUES (:id, :tid, :mid, :mem, :status)",
            [':id' => $id, ':tid' => $tenantId, ':mid' => $meetingId, ':mem' => $memberId, ':status' => $status]
        );
    }

    /**
     * Nombre de demandes terminees/annulees (pour clearHistory).
     */
    public function countFinished(string $meetingId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND status IN ('finished','cancelled')",
            [':mid' => $meetingId, ':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Supprime les demandes terminees/annulees.
     */
    public function deleteFinished(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "DELETE FROM speech_requests
             WHERE meeting_id = :mid AND tenant_id = :tid AND status IN ('finished','cancelled')",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }
}
