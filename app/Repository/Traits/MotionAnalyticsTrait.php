<?php

declare(strict_types=1);

namespace AgVote\Repository\Traits;

/**
 * Motion analytics methods — counting, tallying, stats aggregation.
 *
 * Used by: MotionRepository (via trait inclusion).
 * All methods delegate to AbstractRepository query helpers.
 */
trait MotionAnalyticsTrait {
    public function getTally(string $motionId, string $tenantId): array {
        return $this->selectAll(
            'SELECT b.value, COUNT(*) AS c, COALESCE(SUM(b.weight), 0) AS w
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             WHERE b.motion_id = :mid AND mo.tenant_id = :tid
             GROUP BY b.value',
            [':mid' => $motionId, ':tid' => $tenantId],
        );
    }

    public function listStatsForMeeting(string $meetingId, string $tenantId): array {
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
             WHERE mo.meeting_id = :mid AND mo.tenant_id = :tid
             GROUP BY mo.id, mo.title, mo.manual_total, mo.manual_for, mo.manual_against, mo.manual_abstain
             ORDER BY mo.title",
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function countDistinctVoters(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(DISTINCT b.member_id)
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             WHERE mo.meeting_id = :mid AND mo.tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    public function maxManualTotal(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT MAX(manual_total) FROM motions WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    public function countBadClosedMotions(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM motions mo
               WHERE mo.meeting_id = :mid
                 AND mo.tenant_id = :tid
                 AND mo.closed_at IS NOT NULL
                 AND NOT (
                   (mo.manual_total > 0 AND (coalesce(mo.manual_for,0)+coalesce(mo.manual_against,0)+coalesce(mo.manual_abstain,0)) = mo.manual_total)
                   OR EXISTS (SELECT 1 FROM ballots b WHERE b.motion_id = mo.id)
                 )',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    public function countConsolidatedMotions(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM motions WHERE meeting_id = :mid AND tenant_id = :tid AND closed_at IS NOT NULL AND official_source IS NOT NULL',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    public function countAll(string $tenantId): int {
        return (int) ($this->scalar('SELECT COUNT(*) FROM motions WHERE tenant_id = :tid', [':tid' => $tenantId]) ?? 0);
    }

    public function countForMeeting(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    public function countWorkflowSummary(string $meetingId, string $tenantId): array {
        $row = $this->selectOne(
            'SELECT count(*) AS total,
                    sum(CASE WHEN opened_at IS NOT NULL AND closed_at IS NULL THEN 1 ELSE 0 END) AS open
             FROM motions WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
        return $row ?: ['total' => 0, 'open' => 0];
    }
}
