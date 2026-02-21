<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Aggregation queries for meeting dashboards.
 *
 * These cross-table counts were extracted from MeetingRepository.
 * Each method queries a specific domain table (motions, ballots,
 * attendances, proxies, members, audit_events) scoped by meeting_id.
 */
class MeetingStatsRepository extends AbstractRepository
{
    /**
     * Motion counters for a meeting (for meeting_status).
     */
    public function countMotionStats(string $meetingId): array
    {
        $row = $this->selectOne(
            "SELECT
              COUNT(*) AS total_motions,
              SUM(CASE WHEN mo.closed_at IS NULL THEN 1 ELSE 0 END) AS open_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL
                        AND (mo.manual_total IS NULL OR mo.manual_total <= 0)
                   THEN 1 ELSE 0 END) AS closed_without_tally
             FROM motions mo WHERE mo.meeting_id = :meeting_id",
            [':meeting_id' => $meetingId]
        );
        return $row ?: ['total_motions' => 0, 'open_motions' => 0, 'closed_motions' => 0, 'closed_without_tally' => 0];
    }

    /**
     * Total active members for a tenant.
     */
    public function countActiveMembers(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Count present attendees for a meeting.
     */
    public function countPresent(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances
             WHERE meeting_id = :mid AND mode IN ('present', 'remote')",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxy(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND mode = 'proxy'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countClosedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countOpenMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions
             WHERE meeting_id = :mid AND opened_at IS NOT NULL AND closed_at IS NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countAdoptedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'adopted'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countRejectedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'rejected'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countBallots(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function sumBallotWeight(string $meetingId): float
    {
        return (float)($this->scalar(
            "SELECT COALESCE(SUM(b.weight), 0) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxies(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countIncidents(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM audit_events
             WHERE resource_type = 'meeting' AND resource_id = :mid
               AND action LIKE '%incident%'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countManualVotes(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid AND b.source = 'manual'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * List state transitions (reference data).
     */
    public function listStateTransitions(): array
    {
        return $this->selectAll(
            "SELECT from_status, to_status, required_role, description FROM meeting_state_transitions ORDER BY from_status, to_status"
        );
    }
}
