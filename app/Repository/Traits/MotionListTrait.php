<?php

declare(strict_types=1);

namespace AgVote\Repository\Traits;

/**
 * Motion listing methods — multi-record queries for various views.
 *
 * Used by: MotionRepository (via trait inclusion).
 * All methods delegate to AbstractRepository::selectAll().
 */
trait MotionListTrait {
    public function listForMeetingJson(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT json_agg(t ORDER BY t.position ASC NULLS LAST, t.created_at ASC) AS motions
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
                WHERE mo.meeting_id = :mid AND mo.tenant_id = :tid
             ) AS t',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listClosedForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id FROM motions WHERE meeting_id = :mid AND tenant_id = :tid AND closed_at IS NOT NULL ORDER BY closed_at ASC',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listForReport(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT
               id, title, description, opened_at, closed_at,
               vote_policy_id, quorum_policy_id,
               official_source, official_for, official_against, official_abstain, official_total,
               decision, decision_reason,
               manual_total, manual_for, manual_against, manual_abstain
             FROM motions
             WHERE meeting_id = :mid AND tenant_id = :tid
             ORDER BY position ASC NULLS LAST, created_at ASC',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listForQuorumDisplay(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id, title, status, opened_at, closed_at, quorum_policy_id
             FROM motions
             WHERE meeting_id = :m AND tenant_id = :tid
             ORDER BY position NULLS LAST, created_at ASC',
            [':m' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listOpenable(string $tenantId, string $meetingId, int $limit = 100): array {
        return $this->selectAll(
            'SELECT id, title
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NULL AND closed_at IS NULL
             ORDER BY position NULLS LAST, created_at ASC
             LIMIT ' . max(1, $limit),
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    public function listClosedWithManualTally(string $tenantId, string $meetingId): array {
        return $this->selectAll(
            'SELECT id, title, manual_total, manual_for, manual_against, manual_abstain
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND closed_at IS NOT NULL
             ORDER BY closed_at ASC',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    public function listResultsExportForMeeting(string $meetingId, string $tenantId): array {
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
             WHERE mo.meeting_id = :mid AND mo.tenant_id = :tid
             GROUP BY mo.id, mo.title, mo.position, mo.opened_at, mo.closed_at, mo.decision, mo.decision_reason
             ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC",
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listForReportGeneration(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT title, evote_results
             FROM motions
             WHERE meeting_id = :mid AND tenant_id = :tid
             ORDER BY COALESCE(position, 0) ASC',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listClosedForMeetingWithManualTally(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id, title, manual_total, manual_for, manual_against, manual_abstain, opened_at, closed_at
             FROM motions
             WHERE meeting_id = :mid AND tenant_id = :tid AND closed_at IS NOT NULL
             ORDER BY closed_at ASC NULLS LAST',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listClosedWithoutVotes(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT m.id, m.title
             FROM motions m
             LEFT JOIN ballots b ON b.motion_id = m.id
             WHERE m.meeting_id = :mid AND m.tenant_id = :tid AND m.closed_at IS NOT NULL
             GROUP BY m.id, m.title
             HAVING COUNT(b.id) = 0',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function listUnclosed(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id, title, opened_at
             FROM motions
             WHERE meeting_id = :mid
               AND tenant_id = :tid
               AND opened_at IS NOT NULL
               AND closed_at IS NULL
             ORDER BY opened_at',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }
}
