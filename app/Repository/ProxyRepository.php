<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les procurations.
 */
class ProxyRepository extends AbstractRepository {
    /**
     * Liste les procurations d'une seance avec noms mandant/mandataire.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT
              p.id,
              p.meeting_id,
              p.giver_member_id,
              p.giver_member_id AS giver_id,
              g.full_name AS giver_name,
              p.receiver_member_id,
              p.receiver_member_id AS receiver_id,
              r.full_name AS receiver_name,
              g.voting_power,
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
            ORDER BY g.full_name ASC',
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Trouve une procuration par ID avec les noms des membres.
     */
    public function findWithNames(string $proxyId, string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT p.id, g.full_name AS giver_name, r.full_name AS receiver_name
             FROM proxies p
             JOIN members g ON g.id = p.giver_member_id
             JOIN members r ON r.id = p.receiver_member_id
             WHERE p.id = :proxy_id AND p.meeting_id = :meeting_id AND p.tenant_id = :tid',
            [':proxy_id' => $proxyId, ':meeting_id' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Supprime une procuration.
     */
    public function deleteProxy(string $proxyId, string $meetingId, string $tenantId): int {
        return $this->execute(
            'DELETE FROM proxies WHERE id = :id AND meeting_id = :meeting_id AND tenant_id = :tid',
            [':id' => $proxyId, ':meeting_id' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Verifie la coherence tenant pour une seance + un membre.
     */
    public function countTenantCoherence(string $meetingId, string $tenantId, string $memberId): int {
        return (int) ($this->scalar(
            'SELECT count(*)
             FROM meetings me
             JOIN members m ON m.tenant_id = me.tenant_id
             WHERE me.id = :meeting_id
               AND me.tenant_id = :tenant_id
               AND m.id = :member_id',
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId, ':member_id' => $memberId],
        ) ?? 0);
    }

    /**
     * Compte les procurations actives ou un membre est mandant (pour detecter les chaines).
     */
    public function countActiveAsGiver(string $meetingId, string $memberId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :member_id
               AND tenant_id = :tid
               AND revoked_at IS NULL',
            [':meeting_id' => $meetingId, ':member_id' => $memberId, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Same as countActiveAsGiver but with FOR UPDATE lock (for use within transactions).
     */
    public function countActiveAsGiverForUpdate(string $meetingId, string $memberId, string $tenantId): int {
        $rows = $this->selectAll(
            'SELECT id FROM proxies
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :member_id
               AND tenant_id = :tid
               AND revoked_at IS NULL
             FOR UPDATE',
            [':meeting_id' => $meetingId, ':member_id' => $memberId, ':tid' => $tenantId],
        );
        return count($rows);
    }

    /**
     * Compte les procurations actives recues par un mandataire (pour le plafond).
     */
    public function countActiveAsReceiver(string $meetingId, string $memberId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND receiver_member_id = :member_id
               AND tenant_id = :tid
               AND revoked_at IS NULL',
            [':meeting_id' => $meetingId, ':member_id' => $memberId, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Same as countActiveAsReceiver but with FOR UPDATE lock (for use within transactions).
     */
    public function countActiveAsReceiverForUpdate(string $meetingId, string $memberId, string $tenantId): int {
        $rows = $this->selectAll(
            'SELECT id FROM proxies
             WHERE meeting_id = :meeting_id
               AND receiver_member_id = :member_id
               AND tenant_id = :tid
               AND revoked_at IS NULL
             FOR UPDATE',
            [':meeting_id' => $meetingId, ':member_id' => $memberId, ':tid' => $tenantId],
        );
        return count($rows);
    }

    /**
     * Revoque les procurations actives d'un mandant.
     */
    public function revokeForGiver(string $meetingId, string $giverMemberId, string $tenantId = ''): void {
        if ($tenantId !== '') {
            $this->execute(
                'UPDATE proxies SET revoked_at = now()
                 WHERE meeting_id = :meeting_id
                   AND giver_member_id = :giver
                   AND tenant_id = :tid
                   AND revoked_at IS NULL',
                [':meeting_id' => $meetingId, ':giver' => $giverMemberId, ':tid' => $tenantId],
            );
        } else {
            $this->execute(
                'UPDATE proxies SET revoked_at = now()
                 WHERE meeting_id = :meeting_id
                   AND giver_member_id = :giver
                   AND revoked_at IS NULL',
                [':meeting_id' => $meetingId, ':giver' => $giverMemberId],
            );
        }
    }

    /**
     * Cree ou remplace une procuration (UPSERT).
     */
    public function upsertProxy(
        string $tenantId,
        string $meetingId,
        string $giverMemberId,
        string $receiverMemberId,
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
            ],
        );
    }

    /**
     * Verifie si une procuration active existe entre mandant et mandataire.
     */
    public function hasActiveProxy(string $meetingId, string $giverMemberId, string $receiverMemberId, string $tenantId): bool {
        $count = (int) ($this->scalar(
            'SELECT count(*) FROM proxies
             WHERE meeting_id = :meeting_id
               AND giver_member_id = :giver
               AND receiver_member_id = :receiver
               AND tenant_id = :tid
               AND revoked_at IS NULL',
            [':meeting_id' => $meetingId, ':giver' => $giverMemberId, ':receiver' => $receiverMemberId, ':tid' => $tenantId],
        ) ?? 0);
        return $count > 0;
    }

    // =========================================================================
    // PROXY ANALYSIS (migrated from MeetingRepository)
    // =========================================================================

    /**
     * Count active proxies for a meeting.
     */
    public function countActive(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM proxies
             WHERE meeting_id = :mid AND tenant_id = :tid AND revoked_at IS NULL',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * List distinct giver_member_id of active proxies for a meeting.
     */
    public function listDistinctGivers(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT DISTINCT giver_member_id FROM proxies WHERE meeting_id = :mid AND tenant_id = :tid AND revoked_at IS NULL',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * List receivers exceeding the proxy ceiling.
     */
    public function listCeilingViolations(string $tenantId, string $meetingId, int $maxPerReceiver): array {
        return $this->selectAll(
            'SELECT receiver_member_id, COUNT(*) AS c
             FROM proxies
             WHERE tenant_id = :tid AND meeting_id = :mid AND revoked_at IS NULL
             GROUP BY receiver_member_id
             HAVING COUNT(*) > :mx
             ORDER BY c DESC',
            [':tid' => $tenantId, ':mid' => $meetingId, ':mx' => $maxPerReceiver],
        );
    }

    /**
     * List orphan proxies (receiver is absent).
     */
    public function listOrphans(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            "SELECT p.id, giver.full_name AS giver_name, receiver.full_name AS receiver_name
             FROM proxies p
             JOIN members giver ON giver.id = p.giver_member_id
             JOIN members receiver ON receiver.id = p.receiver_member_id
             LEFT JOIN attendances a ON a.meeting_id = :mid1 AND a.member_id = p.receiver_member_id
             WHERE p.meeting_id = :mid2
               AND p.tenant_id = :tid
               AND (a.id IS NULL OR a.mode NOT IN ('present', 'remote'))",
            [':mid1' => $meetingId, ':mid2' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * List proxies for report generation.
     */
    public function listForReport(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT p.id,
                    g.full_name AS giver_name,
                    r.full_name AS receiver_name,
                    g.voting_power AS giver_voting_power,
                    p.created_at
             FROM proxies p
             JOIN members g ON g.id = p.giver_member_id
             JOIN members r ON r.id = p.receiver_member_id
             WHERE p.meeting_id = :mid AND p.tenant_id = :tid AND p.revoked_at IS NULL
             ORDER BY g.full_name',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Find proxy cycles for a meeting.
     */
    public function findCycles(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT p1.giver_member_id, p1.receiver_member_id,
                    g1.full_name AS giver_name, r1.full_name AS receiver_name
             FROM proxies p1
             JOIN proxies p2 ON p2.meeting_id = p1.meeting_id
                 AND p2.giver_member_id = p1.receiver_member_id
                 AND p2.receiver_member_id = p1.giver_member_id
                 AND p2.revoked_at IS NULL
                 AND p2.tenant_id = p1.tenant_id
             JOIN members g1 ON g1.id = p1.giver_member_id
             JOIN members r1 ON r1.id = p1.receiver_member_id
             WHERE p1.meeting_id = :mid AND p1.tenant_id = :tid AND p1.revoked_at IS NULL',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }
}
