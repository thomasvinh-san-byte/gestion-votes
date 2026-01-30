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
              i.token,
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
     */
    public function upsert(
        string $tenantId,
        string $meetingId,
        string $memberId,
        ?string $email,
        string $token,
        string $status = 'pending'
    ): void {
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token, status, updated_at)
             VALUES (:tenant_id, :meeting_id, :member_id, :email, :token, :status, now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET
               email = EXCLUDED.email,
               token = EXCLUDED.token,
               status = EXCLUDED.status,
               updated_at = now(),
               responded_at = NULL",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':member_id' => $memberId,
                ':email' => $email,
                ':token' => $token,
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
     * Trouve une invitation par token.
     */
    public function findByToken(string $token): ?array
    {
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
     */
    public function upsertSent(
        string $tenantId,
        string $meetingId,
        string $memberId,
        ?string $email,
        string $token
    ): void {
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token, status, sent_at, updated_at)
             VALUES (:tenant_id, :meeting_id, :member_id, :email, :token, 'sent', now(), now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET token = EXCLUDED.token,
                           email = COALESCE(EXCLUDED.email, invitations.email),
                           status = 'sent',
                           sent_at = now(),
                           updated_at = now()",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':member_id' => $memberId,
                ':email' => $email,
                ':token' => $token,
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
        $this->execute(
            "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token, status, sent_at, updated_at)
             VALUES (:tid, :mid, :uid, :email, :token, :status, :sent_at, now())
             ON CONFLICT (tenant_id, meeting_id, member_id)
             DO UPDATE SET token=EXCLUDED.token,
                           email=COALESCE(EXCLUDED.email, invitations.email),
                           status=EXCLUDED.status,
                           sent_at=EXCLUDED.sent_at,
                           updated_at=now()",
            [
                ':tid' => $tenantId, ':mid' => $meetingId, ':uid' => $memberId,
                ':email' => $email, ':token' => $token,
                ':status' => $status, ':sent_at' => $sentAt,
            ]
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
}
