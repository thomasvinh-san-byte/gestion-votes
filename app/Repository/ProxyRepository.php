<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les procurations.
 */
class ProxyRepository extends AbstractRepository
{
    /**
     * Liste les procurations d'une seance avec noms mandant/mandataire.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
              p.id,
              p.meeting_id,
              p.giver_member_id,
              g.full_name AS giver_name,
              p.receiver_member_id,
              r.full_name AS receiver_name,
              p.scope,
              p.created_at,
              p.revoked_at
            FROM meetings me
            JOIN proxies p ON p.meeting_id = me.id
            JOIN members g ON g.id = p.giver_member_id
            JOIN members r ON r.id = p.receiver_member_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
              AND g.tenant_id = me.tenant_id
              AND r.tenant_id = me.tenant_id
            ORDER BY g.full_name ASC",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Trouve une procuration par ID avec les noms des membres.
     */
    public function findWithNames(string $proxyId, string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT p.id, g.full_name AS giver_name, r.full_name AS receiver_name
             FROM proxies p
             JOIN members g ON g.id = p.giver_member_id
             JOIN members r ON r.id = p.receiver_member_id
             WHERE p.id = :proxy_id AND p.meeting_id = :meeting_id",
            [':proxy_id' => $proxyId, ':meeting_id' => $meetingId]
        );
    }

    /**
     * Supprime une procuration.
     */
    public function deleteProxy(string $proxyId, string $meetingId): int
    {
        return $this->execute(
            "DELETE FROM proxies WHERE id = :id AND meeting_id = :meeting_id",
            [':id' => $proxyId, ':meeting_id' => $meetingId]
        );
    }

    /**
     * Verifie la coherence tenant pour une seance + un membre.
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
     * Compte les procurations actives ou un membre est mandant (pour detecter les chaines).
     */
    public function countActiveAsGiver(string $meetingId, string $memberId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :member_id
               AND revoked_at IS NULL",
            [':meeting_id' => $meetingId, ':member_id' => $memberId]
        ) ?? 0);
    }

    /**
     * Compte les procurations actives recues par un mandataire (pour le plafond).
     */
    public function countActiveAsReceiver(string $meetingId, string $memberId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND receiver_member_id = :member_id
               AND revoked_at IS NULL",
            [':meeting_id' => $meetingId, ':member_id' => $memberId]
        ) ?? 0);
    }

    /**
     * Revoque les procurations actives d'un mandant.
     */
    public function revokeForGiver(string $meetingId, string $giverMemberId): void
    {
        $this->execute(
            "UPDATE proxies SET revoked_at = now()
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :giver
               AND revoked_at IS NULL",
            [':meeting_id' => $meetingId, ':giver' => $giverMemberId]
        );
    }

    /**
     * Cree ou remplace une procuration (UPSERT).
     */
    public function upsertProxy(
        string $tenantId,
        string $meetingId,
        string $giverMemberId,
        string $receiverMemberId
    ): void {
        $this->execute(
            "INSERT INTO proxies (tenant_id, meeting_id, giver_member_id, receiver_member_id, scope)
             VALUES (:tenant_id, :meeting_id, :giver, :receiver, 'full')
             ON CONFLICT (tenant_id, meeting_id, giver_member_id)
             DO UPDATE SET
               receiver_member_id = EXCLUDED.receiver_member_id,
               scope = 'full',
               revoked_at = NULL",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':giver' => $giverMemberId,
                ':receiver' => $receiverMemberId,
            ]
        );
    }

    /**
     * Verifie si une procuration active existe entre mandant et mandataire.
     */
    public function hasActiveProxy(string $meetingId, string $giverMemberId, string $receiverMemberId): bool
    {
        $count = (int)($this->scalar(
            "SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :giver
               AND receiver_member_id = :receiver
               AND revoked_at IS NULL",
            [':meeting_id' => $meetingId, ':giver' => $giverMemberId, ':receiver' => $receiverMemberId]
        ) ?? 0);
        return $count > 0;
    }
}
