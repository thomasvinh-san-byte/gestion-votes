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

    /**
     * Contexte quorum pour une motion (QuorumEngine).
     */
    public function findWithQuorumContext(string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id,
                    mo.quorum_policy_id AS motion_quorum_policy_id,
                    mo.opened_at AS motion_opened_at,
                    mt.tenant_id, mt.quorum_policy_id AS meeting_quorum_policy_id,
                    COALESCE(mt.convocation_no, 1) AS convocation_no
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id
             WHERE mo.id = :id",
            [':id' => $motionId]
        );
    }

    /**
     * Contexte vote pour une motion (VoteEngine).
     */
    public function findWithVoteContext(string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT m.id AS motion_id, m.title AS motion_title,
                    m.vote_policy_id, m.secret,
                    mt.id AS meeting_id, mt.tenant_id,
                    mt.quorum_policy_id,
                    mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.id = :id",
            [':id' => $motionId]
        );
    }

    /**
     * Contexte officiel pour une motion (OfficialResultsService, MeetingResultsService).
     */
    public function findWithOfficialContext(string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT m.id, m.title, m.meeting_id,
                    m.vote_policy_id, m.quorum_policy_id,
                    m.secret, m.closed_at,
                    m.manual_total, m.manual_for, m.manual_against, m.manual_abstain,
                    mt.tenant_id,
                    mt.quorum_policy_id AS meeting_quorum_policy_id,
                    mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.id = :id",
            [':id' => $motionId]
        );
    }

    /**
     * Liste les motions fermees d'une seance (pour consolidation).
     */
    public function listClosedForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL ORDER BY closed_at ASC",
            [':mid' => $meetingId]
        );
    }

    /**
     * Motions fermees sans resultat exploitable (MeetingValidator).
     */
    public function countBadClosedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM motions mo
               WHERE mo.meeting_id = :mid
                 AND mo.closed_at IS NOT NULL
                 AND NOT (
                   (mo.manual_total > 0 AND (coalesce(mo.manual_for,0)+coalesce(mo.manual_against,0)+coalesce(mo.manual_abstain,0)) = mo.manual_total)
                   OR EXISTS (SELECT 1 FROM ballots b WHERE b.motion_id = mo.id)
                 )",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Motions fermees avec official_source renseigne (MeetingValidator).
     */
    public function countConsolidatedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL AND official_source IS NOT NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Contexte ballot (BallotsService): motion + meeting status + tenant.
     */
    public function findWithBallotContext(string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT
              m.id          AS motion_id,
              m.opened_at   AS motion_opened_at,
              m.closed_at   AS motion_closed_at,
              mt.id         AS meeting_id,
              mt.status     AS meeting_status,
              mt.validated_at AS meeting_validated_at,
              mt.tenant_id  AS tenant_id
            FROM motions m
            JOIN meetings mt ON mt.id = m.meeting_id
            WHERE m.id = :mid",
            [':mid' => $motionId]
        );
    }

    /**
     * Liste les motions d'une seance pour rapport (avec colonnes officielles).
     */
    public function listForReport(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
               id, title, description, opened_at, closed_at,
               vote_policy_id, quorum_policy_id,
               official_source, official_for, official_against, official_abstain, official_total,
               decision, decision_reason,
               manual_total, manual_for, manual_against, manual_abstain
             FROM motions
             WHERE meeting_id = :mid
             ORDER BY position ASC NULLS LAST, created_at ASC",
            [':mid' => $meetingId]
        );
    }

    /**
     * Liste les motions "ouvrables" (draft, pas encore ouverte ni fermee) pour le dashboard.
     */
    public function listOpenable(string $tenantId, string $meetingId, int $limit = 100): array
    {
        return $this->selectAll(
            "SELECT id, title
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NULL AND closed_at IS NULL
             ORDER BY position NULLS LAST, created_at ASC
             LIMIT " . max(1, $limit),
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les motions fermees avec donnees de comptage manuel (pour readiness check).
     */
    public function listClosedWithManualTally(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id, title, manual_total, manual_for, manual_against, manual_abstain
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND closed_at IS NOT NULL
             ORDER BY closed_at ASC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Trouve une motion dans une seance specifique avec etat d'ouverture.
     */
    public function findForMeetingWithState(string $tenantId, string $motionId, string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid",
            [':tid' => $tenantId, ':id' => $motionId, ':mid' => $meetingId]
        );
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

    /**
     * Persiste les resultats officiels d'une motion (OfficialResultsService).
     */
    public function updateOfficialResults(
        string $motionId,
        string $source,
        float $for,
        float $against,
        float $abstain,
        float $total,
        string $decision,
        string $reason
    ): void {
        $this->execute(
            "UPDATE motions SET
               official_source = :src, official_for = :f, official_against = :a,
               official_abstain = :ab, official_total = :t,
               decision = :d, decision_reason = :r, decided_at = NOW()
             WHERE id = :id",
            [
                ':src' => $source, ':f' => $for, ':a' => $against,
                ':ab' => $abstain, ':t' => $total,
                ':d' => $decision, ':r' => $reason, ':id' => $motionId,
            ]
        );
    }

    /**
     * Ajoute les colonnes official_* si absentes (migration best-effort).
     */
    public function ensureOfficialColumns(): void
    {
        try {
            $this->execute(
                "ALTER TABLE motions
                  ADD COLUMN IF NOT EXISTS official_source text,
                  ADD COLUMN IF NOT EXISTS official_for double precision,
                  ADD COLUMN IF NOT EXISTS official_against double precision,
                  ADD COLUMN IF NOT EXISTS official_abstain double precision,
                  ADD COLUMN IF NOT EXISTS official_total double precision,
                  ADD COLUMN IF NOT EXISTS decision text,
                  ADD COLUMN IF NOT EXISTS decision_reason text,
                  ADD COLUMN IF NOT EXISTS decided_at timestamptz"
            );
        } catch (\Throwable $e) { /* best-effort */ }
    }
}
