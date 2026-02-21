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
    public function getTally(string $motionId): array {
        return $this->selectAll(
            'SELECT value, COUNT(*) AS c, COALESCE(SUM(weight), 0) AS w
             FROM ballots WHERE motion_id = :mid GROUP BY value',
            [':mid' => $motionId],
        );
    }

    public function listStatsForMeeting(string $meetingId): array {
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
            [':mid' => $meetingId],
        );
    }

    public function countDistinctVoters(string $meetingId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(DISTINCT b.member_id)
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             WHERE mo.meeting_id = :mid',
            [':mid' => $meetingId],
        ) ?? 0);
    }

    public function maxManualTotal(string $meetingId): int {
        return (int) ($this->scalar(
            'SELECT MAX(manual_total) FROM motions WHERE meeting_id = :mid',
            [':mid' => $meetingId],
        ) ?? 0);
    }

    public function countBadClosedMotions(string $meetingId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM motions mo
               WHERE mo.meeting_id = :mid
                 AND mo.closed_at IS NOT NULL
                 AND NOT (
                   (mo.manual_total > 0 AND (coalesce(mo.manual_for,0)+coalesce(mo.manual_against,0)+coalesce(mo.manual_abstain,0)) = mo.manual_total)
                   OR EXISTS (SELECT 1 FROM ballots b WHERE b.motion_id = mo.id)
                 )',
            [':mid' => $meetingId],
        ) ?? 0);
    }

    public function countConsolidatedMotions(string $meetingId): int {
        return (int) ($this->scalar(
            'SELECT count(*) FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL AND official_source IS NOT NULL',
            [':mid' => $meetingId],
        ) ?? 0);
    }

    public function countAll(): int {
        return (int) ($this->scalar('SELECT COUNT(*) FROM motions') ?? 0);
    }

    public function countForMeeting(string $meetingId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM motions WHERE meeting_id = ?',
            [$meetingId],
        ) ?? 0);
    }

    public function countWorkflowSummary(string $meetingId): array {
        $row = $this->selectOne(
            'SELECT count(*) AS total,
                    sum(CASE WHEN opened_at IS NOT NULL AND closed_at IS NULL THEN 1 ELSE 0 END) AS open
             FROM motions WHERE meeting_id = :mid',
            [':mid' => $meetingId],
        );
        return $row ?: ['total' => 0, 'open' => 0];
    }
}
