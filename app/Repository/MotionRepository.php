<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les motions (resolutions).
 *
 * Centralise toutes les requetes SQL de la table motions + ballots associes.
 */
class MotionRepository extends AbstractRepository
{
    // =========================================================================
    // LECTURE
    // =========================================================================

    public function findByIdForTenant(string $motionId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM motions WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Trouve une motion avec informations de la seance (status, validated_at, policies).
     */
    public function findWithMeetingInfo(string $motionId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT
                m.id AS motion_id, m.meeting_id, m.title,
                m.opened_at, m.closed_at,
                m.vote_policy_id, m.quorum_policy_id,
                mt.status AS meeting_status,
                mt.validated_at AS meeting_validated_at,
                mt.quorum_policy_id AS meeting_quorum_policy_id,
                mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.tenant_id = :tid AND m.id = :id",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Trouve une motion avec status de la seance (pour override endpoints).
     */
    public function findWithMeetingStatus(string $motionId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT mo.id, mo.meeting_id, mo.opened_at, mo.closed_at
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id
             WHERE mt.tenant_id = :tid AND mo.id = :id",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Trouve l'agenda avec infos meeting (pour valider le tenant).
     */
    public function findAgendaWithMeeting(string $agendaId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT a.id, a.meeting_id, m.tenant_id
             FROM agendas a
             JOIN meetings m ON m.id = a.meeting_id
             WHERE a.id = :aid AND m.tenant_id = :tid",
            [':aid' => $agendaId, ':tid' => $tenantId]
        );
    }

    /**
     * Trouve la motion actuellement ouverte pour une seance.
     */
    public function findCurrentOpen(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, agenda_id, title, description, secret,
                    vote_policy_id, quorum_policy_id, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             ORDER BY opened_at DESC LIMIT 1",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Trouve une motion ouverte (FOR UPDATE) pour verrouillage concurrent.
     */
    public function findOpenForUpdate(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             LIMIT 1 FOR UPDATE",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les motions d'une seance avec agendas (JSON agrege).
     */
    public function listForMeetingJson(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT json_agg(t) AS motions
             FROM (
                SELECT
                    mo.id AS motion_id, mo.title AS motion_title,
                    mo.description AS motion_description,
                    mo.opened_at, mo.closed_at, mo.secret,
                    mo.tally_status, mo.decision, mo.decision_reason,
                    mo.evote_results, mo.manual_tally,
                    mo.vote_policy_id, mo.quorum_policy_id,
                    a.id AS agenda_id, a.title AS agenda_title, a.idx AS agenda_idx
                FROM motions mo
                LEFT JOIN agendas a ON a.id = mo.agenda_id
                WHERE mo.meeting_id = :mid
                ORDER BY a.idx ASC, mo.created_at ASC
             ) AS t",
            [':mid' => $meetingId]
        );
    }

    /**
     * Tally des bulletins pour une motion.
     */
    public function getTally(string $motionId): array
    {
        return $this->selectAll(
            "SELECT value, COUNT(*) AS c, COALESCE(SUM(weight), 0) AS w
             FROM ballots WHERE motion_id = :mid GROUP BY value",
            [':mid' => $motionId]
        );
    }

    /**
     * Stats detaillees par motion pour une seance (meeting_stats).
     */
    public function listStatsForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
                mo.id AS motion_id, mo.title,
                COUNT(b.id) AS ballots_total,
                COUNT(b.id) FILTER (WHERE b.value = 'for') AS ballots_for,
                COUNT(b.id) FILTER (WHERE b.value = 'against') AS ballots_against,
                COUNT(b.id) FILTER (WHERE b.value = 'abstain') AS ballots_abstain,
                COUNT(b.id) FILTER (WHERE b.value = 'nsp') AS ballots_nsp,
                mo.manual_total, mo.manual_for, mo.manual_against, mo.manual_abstain
             FROM motions mo
             LEFT JOIN ballots b ON b.motion_id = mo.id
             WHERE mo.meeting_id = :mid
             GROUP BY mo.id, mo.title, mo.manual_total, mo.manual_for, mo.manual_against, mo.manual_abstain
             ORDER BY mo.title",
            [':mid' => $meetingId]
        );
    }

    /**
     * Compte les votants distincts pour une seance.
     */
    public function countDistinctVoters(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(DISTINCT b.member_id)
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             WHERE mo.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Max manual_total pour une seance (fallback votants).
     */
    public function maxManualTotal(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT MAX(manual_total) FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    // =========================================================================
    // ECRITURE
    // =========================================================================

    /**
     * Cree une motion.
     */
    public function create(
        string $id,
        string $tenantId,
        string $meetingId,
        string $agendaId,
        string $title,
        string $description,
        bool $secret,
        ?string $votePolicyId,
        ?string $quorumPolicyId
    ): void {
        $this->execute(
            "INSERT INTO motions (id, tenant_id, meeting_id, agenda_id, title, description, secret, vote_policy_id, quorum_policy_id, created_at)
             VALUES (:id, :tid, :mid, :aid, :title, :desc, :secret, NULLIF(:vpid,''), NULLIF(:qpid,''), now())",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':aid' => $agendaId,
                ':title' => $title,
                ':desc' => $description,
                ':secret' => $secret ? 't' : 'f',
                ':vpid' => $votePolicyId ?? '',
                ':qpid' => $quorumPolicyId ?? '',
            ]
        );
    }

    /**
     * Met a jour une motion (titre, description, secret, policies).
     */
    public function update(
        string $motionId,
        string $tenantId,
        string $title,
        string $description,
        bool $secret,
        ?string $votePolicyId,
        ?string $quorumPolicyId
    ): void {
        $this->execute(
            "UPDATE motions
             SET title = :title, description = :desc, secret = :secret,
                 vote_policy_id = NULLIF(:vpid,''), quorum_policy_id = NULLIF(:qpid,'')
             WHERE tenant_id = :tid AND id = :id",
            [
                ':title' => $title,
                ':desc' => $description,
                ':secret' => $secret ? 't' : 'f',
                ':vpid' => $votePolicyId ?? '',
                ':qpid' => $quorumPolicyId ?? '',
                ':tid' => $tenantId,
                ':id' => $motionId,
            ]
        );
    }

    /**
     * Marque une motion comme ouverte (si pas deja ouverte).
     */
    public function markOpened(string $motionId, string $tenantId): int
    {
        return $this->execute(
            "UPDATE motions SET opened_at = COALESCE(opened_at, now()), closed_at = NULL
             WHERE tenant_id = :tid AND id = :id AND closed_at IS NULL",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Marque une motion comme fermee.
     */
    public function markClosed(string $motionId, string $tenantId): void
    {
        $this->execute(
            "UPDATE motions SET closed_at = now()
             WHERE tenant_id = :tid AND id = :id AND closed_at IS NULL",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Supprime une motion.
     */
    public function delete(string $motionId, string $tenantId): void
    {
        $this->execute(
            "DELETE FROM motions WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Met a jour la politique de vote d'une motion.
     */
    public function updateVotePolicy(string $motionId, ?string $policyId): void
    {
        $this->execute(
            "UPDATE motions SET vote_policy_id = :pid, updated_at = NOW() WHERE id = :id",
            [':pid' => $policyId, ':id' => $motionId]
        );
    }

    /**
     * Met a jour la politique de quorum d'une motion.
     */
    public function updateQuorumPolicy(string $motionId, ?string $policyId): void
    {
        $this->execute(
            "UPDATE motions SET quorum_policy_id = :pid, updated_at = NOW() WHERE id = :id",
            [':pid' => $policyId, ':id' => $motionId]
        );
    }
}
