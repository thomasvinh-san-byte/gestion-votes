<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for motions (resolutions).
 *
 * Centralizes all SQL queries for the motions table + associated ballots.
 */
class MotionRepository extends AbstractRepository
{
    // =========================================================================
    // READ
    // =========================================================================

    public function findByIdForTenant(string $motionId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM motions WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Finds a motion by its slug (URL obfuscation).
     */
    public function findBySlugForTenant(string $slug, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM motions WHERE slug = :slug AND tenant_id = :tid",
            [':slug' => $slug, ':tid' => $tenantId]
        );
    }

    /**
     * Finds a motion by ID or slug (dual support).
     * Automatically detects if the identifier is a UUID or slug.
     */
    public function findByIdOrSlugForTenant(string $identifier, string $tenantId): ?array
    {
        // Check if it's a UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $this->findByIdForTenant($identifier, $tenantId);
        }
        // Otherwise, search by slug
        return $this->findBySlugForTenant($identifier, $tenantId);
    }

    /**
     * Finds a motion with meeting info (status, validated_at, policies).
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
     * Finds a motion by ID with lock FOR UPDATE (for transactions).
     * Returns all main columns with locking.
     */
    public function findByIdForTenantForUpdate(string $motionId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT m.id, m.meeting_id, m.agenda_id, m.title, m.description,
                    m.opened_at, m.closed_at, m.secret, m.vote_policy_id, m.quorum_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.tenant_id = :tid AND m.id = :id
             FOR UPDATE",
            [':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Finds a motion with meeting status (for override endpoints).
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
            "SELECT id, agenda_id, title, description, body, secret, position,
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
            "SELECT json_agg(t ORDER BY t.position ASC NULLS LAST, t.created_at ASC) AS motions
             FROM (
                SELECT
                    mo.id AS motion_id, mo.title AS motion_title,
                    mo.description AS motion_description,
                    mo.opened_at, mo.closed_at, mo.secret,
                    mo.tally_status, mo.decision, mo.decision_reason,
                    mo.evote_results, mo.manual_tally,
                    mo.vote_policy_id, mo.quorum_policy_id,
                    mo.position, mo.created_at,
                    a.id AS agenda_id, a.title AS agenda_title, a.idx AS agenda_idx
                FROM motions mo
                LEFT JOIN agendas a ON a.id = mo.agenda_id
                WHERE mo.meeting_id = :mid
             ) AS t",
            [':mid' => $meetingId]
        );
    }

    /**
     * Met a jour la position d'une motion.
     */
    public function updatePosition(string $motionId, string $tenantId, int $position): void
    {
        $this->execute(
            "UPDATE motions SET position = :pos WHERE tenant_id = :tid AND id = :id",
            [':pos' => $position, ':tid' => $tenantId, ':id' => $motionId]
        );
    }

    /**
     * Reordonne toutes les motions d'une seance selon un tableau d'IDs.
     */
    public function reorderAll(string $meetingId, string $tenantId, array $motionIds): void
    {
        foreach ($motionIds as $position => $motionId) {
            $this->execute(
                "UPDATE motions SET position = :pos
                 WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id",
                [':pos' => $position + 1, ':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId]
            );
        }
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
     * Contexte officiel pour une motion (OfficialResultsService).
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
     * Liste les motions d'une seance pour affichage quorum (badge + justification).
     */
    public function listForQuorumDisplay(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id, title, status, opened_at, closed_at, quorum_policy_id
             FROM motions
             WHERE meeting_id = :m
             ORDER BY sort_order NULLS LAST, created_at ASC",
            [':m' => $meetingId]
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

    /**
     * Export CSV: resultats agrege des motions pour une seance.
     */
    public function listResultsExportForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
                mo.title,
                mo.position,
                mo.opened_at,
                mo.closed_at,
                COALESCE(SUM(CASE WHEN b.value = 'for' THEN b.weight ELSE 0 END), 0) AS w_for,
                COALESCE(SUM(CASE WHEN b.value = 'against' THEN b.weight ELSE 0 END), 0) AS w_against,
                COALESCE(SUM(CASE WHEN b.value = 'abstain' THEN b.weight ELSE 0 END), 0) AS w_abstain,
                COALESCE(SUM(CASE WHEN b.value = 'nsp' THEN b.weight ELSE 0 END), 0) AS w_nsp,
                COALESCE(SUM(b.weight), 0) AS w_total,
                COALESCE(COUNT(b.id), 0) AS ballots_count,
                COALESCE(mo.decision, '') AS decision,
                COALESCE(mo.decision_reason, '') AS decision_reason
             FROM motions mo
             LEFT JOIN ballots b ON b.motion_id = mo.id
             WHERE mo.meeting_id = ?
             GROUP BY mo.id, mo.title, mo.position, mo.opened_at, mo.closed_at, mo.decision, mo.decision_reason
             ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC",
            [$meetingId]
        );
    }

    /**
     * Trouve une motion par son ID et meeting_id (colonnes basiques).
     */
    public function findByIdAndMeeting(string $motionId, string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title FROM motions WHERE id = :id AND meeting_id = :mid",
            [':id' => $motionId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste titre et evote_results pour generation de rapport.
     */
    public function listForReportGeneration(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT title, evote_results
             FROM motions
             WHERE meeting_id = :mid
             ORDER BY COALESCE(position, sort_order, 0) ASC",
            [':mid' => $meetingId]
        );
    }

    /**
     * Trouve la motion ouverte pour le projecteur.
     */
    public function findOpenForProjector(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, description, body, secret, position, opened_at
             FROM motions
             WHERE meeting_id = :meeting_id
               AND opened_at IS NOT NULL
               AND closed_at IS NULL
             ORDER BY opened_at DESC
             LIMIT 1",
            [':meeting_id' => $meetingId]
        );
    }

    /**
     * Trouve la derniere motion fermee pour le projecteur.
     */
    public function findLastClosedForProjector(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, description, body, secret, position, closed_at
             FROM motions
             WHERE meeting_id = :meeting_id
               AND closed_at IS NOT NULL
             ORDER BY closed_at DESC
             LIMIT 1",
            [':meeting_id' => $meetingId]
        );
    }

    /**
     * Liste les motions fermees avec donnees de comptage manuel (par meeting_id seul).
     */
    public function listClosedForMeetingWithManualTally(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id, title, manual_total, manual_for, manual_against, manual_abstain, opened_at, closed_at
             FROM motions
             WHERE meeting_id = :mid AND closed_at IS NOT NULL
             ORDER BY closed_at ASC NULLS LAST",
            [':mid' => $meetingId]
        );
    }

    /**
     * Motions closes sans aucun bulletin pour une seance.
     */
    public function listClosedWithoutVotes(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT m.id, m.title
             FROM motions m
             LEFT JOIN ballots b ON b.motion_id = m.id
             WHERE m.meeting_id = :mid AND m.closed_at IS NOT NULL
             GROUP BY m.id, m.title
             HAVING COUNT(b.id) = 0",
            [':mid' => $meetingId]
        );
    }

    /**
     * Compte toutes les motions (global, sans filtre tenant).
     */
    public function countAll(): int
    {
        return (int)($this->scalar("SELECT COUNT(*) FROM motions") ?? 0);
    }

    /**
     * Counts motions for a given meeting.
     */
    public function countForMeeting(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = ?",
            [$meetingId]
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
        ?string $agendaId,
        string $title,
        string $description,
        bool $secret,
        ?string $votePolicyId,
        ?string $quorumPolicyId
    ): void {
        $this->execute(
            "INSERT INTO motions (id, tenant_id, meeting_id, agenda_id, title, description, secret, vote_policy_id, quorum_policy_id, created_at)
             VALUES (:id, :tid, :mid, NULLIF(:aid,'')::uuid, :title, :desc, :secret, NULLIF(:vpid,'')::uuid, NULLIF(:qpid,'')::uuid, now())",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':aid' => $agendaId ?? '',
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
                 vote_policy_id = NULLIF(:vpid,'')::uuid, quorum_policy_id = NULLIF(:qpid,'')::uuid
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

    /**
     * Compteurs motions pour workflow (total + open au sens ouvert non ferme).
     */
    public function countWorkflowSummary(string $meetingId): array
    {
        $row = $this->selectOne(
            "SELECT count(*) AS total,
                    sum(CASE WHEN opened_at IS NOT NULL AND closed_at IS NULL THEN 1 ELSE 0 END) AS open
             FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        );
        return $row ?: ['total' => 0, 'open' => 0];
    }

    /**
     * Prochaine motion non encore ouverte (ordre position/created_at).
     */
    public function findNextNotOpened(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title FROM motions
             WHERE meeting_id = :mid AND opened_at IS NULL
             ORDER BY position ASC NULLS LAST, created_at ASC LIMIT 1",
            [':mid' => $meetingId]
        );
    }

    /**
     * Prochaine motion non encore ouverte (FOR UPDATE, avec tenant).
     */
    public function findNextNotOpenedForUpdate(string $tenantId, string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NULL AND closed_at IS NULL
             ORDER BY COALESCE(position, sort_order, 0) ASC
             LIMIT 1 FOR UPDATE",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Trouve une motion par id + meeting_id (FOR UPDATE, avec tenant).
     */
    public function findByIdAndMeetingForUpdate(string $tenantId, string $meetingId, string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id
             FOR UPDATE",
            [':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId]
        );
    }

    /**
     * Trouve une motion dans une seance avec titre et dates.
     */
    public function findByMeetingWithDates(string $tenantId, string $meetingId, string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id",
            [':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId]
        );
    }

    /**
     * Trouve une motion avec son meeting_id et tenant_id (sans filtre tenant).
     */
    public function findWithMeetingTenant(string $motionId): ?array
    {
        return $this->selectOne(
            "SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id, m.tenant_id
             FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE mo.id = :id",
            [':id' => $motionId]
        );
    }

    /**
     * Met a jour le comptage manuel d'une motion.
     */
    public function updateManualTally(string $motionId, int $total, int $for, int $against, int $abstain): void
    {
        $this->execute(
            "UPDATE motions SET manual_total = :t, manual_for = :f, manual_against = :a, manual_abstain = :ab WHERE id = :id",
            [':t' => $total, ':f' => $for, ':a' => $against, ':ab' => $abstain, ':id' => $motionId]
        );
    }

    /**
     * Reinitialise toutes les motions d'une seance (reset demo).
     */
    public function resetStatesForMeeting(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "UPDATE motions
             SET opened_at = NULL, closed_at = NULL,
                 manual_total = NULL, manual_for = NULL, manual_against = NULL, manual_abstain = NULL,
                 updated_at = now()
             WHERE meeting_id = :mid AND tenant_id = :tid",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Marque une motion comme ouverte (avec filtre meeting_id).
     */
    public function markOpenedInMeeting(string $tenantId, string $motionId, string $meetingId): void
    {
        $this->execute(
            "UPDATE motions
             SET opened_at = COALESCE(opened_at, now()), closed_at = NULL
             WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid AND closed_at IS NULL",
            [':tid' => $tenantId, ':id' => $motionId, ':mid' => $meetingId]
        );
    }

    /**
     * Trouve motion ouverte + ses dates pour validation (motionId + meetingId, sans tenant).
     */
    public function findByIdAndMeetingWithDates(string $motionId, string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, meeting_id, opened_at, closed_at FROM motions WHERE id = :id AND meeting_id = :mid",
            [':id' => $motionId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les motions ouvertes et non fermees (anomalies).
     */
    public function listUnclosed(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id, title, opened_at
             FROM motions
             WHERE meeting_id = :mid
               AND opened_at IS NOT NULL
               AND closed_at IS NULL
             ORDER BY opened_at",
            [':mid' => $meetingId]
        );
    }

    /**
     * Verifie si une motion a ete creee par un utilisateur.
     */
    public function isOwnedByUser(string $motionId, string $userId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM motions WHERE id = :id AND created_by_user_id = :uid",
            [':id' => $motionId, ':uid' => $userId]
        );
    }
}
