<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les invitations.
 */
class InvitationRepository extends AbstractRepository
{
    /**
     * Liste les invitations d'une seance avec infos membre (via service).
     */
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
              i.id,
              i.meeting_id,
              i.member_id,
              m.full_name AS member_name,
              i.email,
              CASE WHEN i.token_hash IS NOT NULL THEN '***' ELSE NULL END AS token,
              i.status,
              i.sent_at,
              i.responded_at,
              i.created_at,
              i.updated_at
            FROM meetings me
            JOIN invitations i ON i.meeting_id = me.id
            JOIN members m ON m.id = i.member_id AND m.tenant_id = me.tenant_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
            ORDER BY m.full_name ASC",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Verifie coherence tenant pour meeting + member.
     */
    public function countTenantCoherence(string $meetingId, string $tenantId, string $memberId): int
    {
        return (int)($this->scalar(
            "SELECT count(*)
             FROM meetings me
             JOIN members m ON m.tenant_id = me.tenant_id
             WHERE me.id = :meeting_id
               AND me.tenant_id = :tenant_id
               AND m.id = :member_id",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId, ':member_id' => $memberId]
        ) ?? 0);
    }

    /**
     * Cree ou met a jour une invitation (UPSERT).
     * Stocke le token_hash (SHA-256) au lieu du token brut pour la securite.
     */
    public function upsert(
        string $tenantId,
        string $meetingId,
        string $memberId,
        ?string $email,
        string $token,
        string $status = 'pending'
    ): void {
        $tokenHash = hash('sha256', $token);
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token_hash, status, updated_at)
             VALUES (:tenant_id, :meeting_id, :member_id, :email, :token_hash, :status, now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET
               email = EXCLUDED.email,
               token_hash = EXCLUDED.token_hash,
               token = NULL,
               status = EXCLUDED.status,
               updated_at = now(),
               responded_at = NULL",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':member_id' => $memberId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
                ':status' => $status,
            ]
        );
    }

    /**
     * Trouve une invitation par meeting + member.
     */
    public function findByMeetingAndMember(string $meetingId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT id, meeting_id, member_id, email, token, status, created_at, updated_at
             FROM invitations
             WHERE meeting_id = :meeting_id AND member_id = :member_id",
            [':meeting_id' => $meetingId, ':member_id' => $memberId]
        );
    }

    /**
     * Trouve une invitation par token (via hash lookup).
     * Cherche d'abord par token_hash, fallback sur token brut pour la retro-compatibilite.
     */
    public function findByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        // Lookup par hash (securise)
        $row = $this->selectOne(
            "SELECT id, meeting_id, member_id, status
             FROM invitations
             WHERE token_hash = :hash
             LIMIT 1",
            [':hash' => $tokenHash]
        );
        if ($row) {
            return $row;
        }

        // Fallback: lookup par token brut (anciennes invitations non migrees)
        return $this->selectOne(
            "SELECT id, meeting_id, member_id, status
             FROM invitations
             WHERE token = :token
             LIMIT 1",
            [':token' => $token]
        );
    }

    /**
     * UPSERT specifique pour l'envoi d'invitation (status=sent, sent_at, COALESCE email).
     * Stocke le token_hash au lieu du token brut.
     */
    public function upsertSent(
        string $tenantId,
        string $meetingId,
        string $memberId,
        ?string $email,
        string $token
    ): void {
        $tokenHash = hash('sha256', $token);
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token_hash, status, sent_at, updated_at)
             VALUES (:tenant_id, :meeting_id, :member_id, :email, :token_hash, 'sent', now(), now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET token_hash = EXCLUDED.token_hash,
                           token = NULL,
                           email = COALESCE(EXCLUDED.email, invitations.email),
                           status = 'sent',
                           sent_at = now(),
                           updated_at = now()",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':member_id' => $memberId,
                ':email' => $email,
                ':token_hash' => $tokenHash,
            ]
        );
    }

    /**
     * Trouve le statut d'une invitation par meeting et member.
     */
    public function findStatusByMeetingAndMember(string $meetingId, string $memberId): ?string
    {
        $val = $this->scalar(
            "SELECT status FROM invitations WHERE meeting_id = :mid AND member_id = :uid LIMIT 1",
            [':mid' => $meetingId, ':uid' => $memberId]
        );
        return $val !== null ? (string)$val : null;
    }

    /**
     * Upsert pour envoi en masse (avec status et sent_at parametrables).
     * Stocke le token_hash au lieu du token brut.
     */
    public function upsertBulk(
        string $tenantId,
        string $meetingId,
        string $memberId,
        string $email,
        string $token,
        string $status,
        ?string $sentAt
    ): void {
        $tokenHash = hash('sha256', $token);
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token_hash, status, sent_at, updated_at)
             VALUES (:tid, :mid, :uid, :email, :token_hash, :status, :sent_at, now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET token_hash=EXCLUDED.token_hash,
                           token=NULL,
                           email=COALESCE(EXCLUDED.email, invitations.email),
                           status=EXCLUDED.status,
                           sent_at=EXCLUDED.sent_at,
                           updated_at=now()",
            [
                ':tid' => $tenantId, ':mid' => $meetingId, ':uid' => $memberId,
                ':email' => $email, ':token_hash' => $tokenHash,
                ':status' => $status, ':sent_at' => $sentAt,
            ]
        );
    }

    /**
     * Liste les tokens/invitations pour rapport PV (avec nom du membre).
     */
    public function listTokensForReport(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT m.full_name, i.created_at, i.revoked_at, i.last_used_at
             FROM invitations i
             JOIN members m ON m.id = i.member_id
             WHERE i.meeting_id = :mid
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId]
        );
    }

    /**
     * Marque une invitation comme bounced (echec envoi).
     */
    public function markBounced(string $meetingId, string $memberId): void
    {
        $this->execute(
            "UPDATE invitations SET status='bounced', updated_at=now()
             WHERE meeting_id = :mid AND member_id = :uid",
            [':mid' => $meetingId, ':uid' => $memberId]
        );
    }

    /**
     * Marque une invitation comme acceptee.
     */
    public function markAccepted(string $id): void
    {
        $this->execute(
            "UPDATE invitations
             SET status = 'accepted',
                 responded_at = coalesce(responded_at, now()),
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Marque une invitation comme ouverte (si pending/sent).
     */
    public function markOpened(string $id): void
    {
        $this->execute(
            "UPDATE invitations
             SET status = CASE WHEN status IN ('pending','sent') THEN 'opened' ELSE status END,
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Marque une invitation comme envoyee.
     */
    public function markSent(string $id): void
    {
        $this->execute(
            "UPDATE invitations
             SET status = 'sent',
                 sent_at = COALESCE(sent_at, now()),
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Incremente le compteur d'ouvertures.
     */
    public function incrementOpenCount(string $id): void
    {
        $this->execute(
            "UPDATE invitations
             SET open_count = COALESCE(open_count, 0) + 1,
                 opened_at = COALESCE(opened_at, now()),
                 status = CASE WHEN status IN ('pending','sent') THEN 'opened' ELSE status END,
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Incremente le compteur de clics.
     */
    public function incrementClickCount(string $id): void
    {
        $this->execute(
            "UPDATE invitations
             SET click_count = COALESCE(click_count, 0) + 1,
                 clicked_at = COALESCE(clicked_at, now()),
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Finds the tenant_id of an invitation by its ID.
     * Used for email tracking (pixel/redirect).
     */
    public function findTenantById(string $id): ?string
    {
        $val = $this->scalar(
            "SELECT tenant_id FROM invitations WHERE id = :id LIMIT 1",
            [':id' => $id]
        );
        return $val !== null ? (string)$val : null;
    }

    /**
     * Statistiques d'envoi pour une seance.
     */
    public function getStatsForMeeting(string $meetingId, string $tenantId): array
    {
        $row = $this->selectOne(
            "SELECT
                 COUNT(*) as total,
                 COUNT(*) FILTER (WHERE status = 'pending') as pending,
                 COUNT(*) FILTER (WHERE status = 'sent') as sent,
                 COUNT(*) FILTER (WHERE status = 'opened') as opened,
                 COUNT(*) FILTER (WHERE status = 'accepted') as accepted,
                 COUNT(*) FILTER (WHERE status = 'declined') as declined,
                 COUNT(*) FILTER (WHERE status = 'bounced') as bounced,
                 COALESCE(SUM(open_count), 0) as total_opens,
                 COALESCE(SUM(click_count), 0) as total_clicks
             FROM invitations
             WHERE meeting_id = :meeting_id AND tenant_id = :tenant_id",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId]
        );

        return $row ?: [
            'total' => 0, 'pending' => 0, 'sent' => 0, 'opened' => 0,
            'accepted' => 0, 'declined' => 0, 'bounced' => 0,
            'total_opens' => 0, 'total_clicks' => 0,
        ];
    }
}
