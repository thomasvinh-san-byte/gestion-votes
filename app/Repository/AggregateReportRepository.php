<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les rapports agreges multi-seances.
 */
class AggregateReportRepository extends AbstractRepository
{
    /**
     * Rapport de participation: taux de presence par membre sur N seances.
     */
    public function getParticipationReport(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        return $this->selectAll(
            "SELECT
                m.id AS member_id,
                m.full_name,
                m.email,
                m.voting_power,
                COUNT(DISTINCT mt.id) AS total_meetings,
                COUNT(DISTINCT a.meeting_id) FILTER (WHERE a.mode IN ('present', 'remote')) AS attended_present,
                COUNT(DISTINCT a.meeting_id) FILTER (WHERE a.mode = 'proxy') AS attended_proxy,
                COUNT(DISTINCT a.meeting_id) FILTER (WHERE a.mode = 'excused') AS excused,
                COUNT(DISTINCT mt.id) - COUNT(DISTINCT a.meeting_id) AS absent,
                ROUND(
                    CASE WHEN COUNT(DISTINCT mt.id) > 0
                    THEN COUNT(DISTINCT a.meeting_id) FILTER (WHERE a.mode IN ('present', 'remote', 'proxy'))::numeric
                         / COUNT(DISTINCT mt.id) * 100
                    ELSE 0 END, 1
                ) AS participation_rate
            FROM members m
            CROSS JOIN (
                SELECT id, scheduled_at FROM meetings
                WHERE tenant_id = :tid AND status IN ('live', 'closed', 'validated', 'archived')
                {$meetingFilter}
            ) mt
            LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = mt.id
            WHERE m.tenant_id = :tid AND m.is_active = true AND m.deleted_at IS NULL
            GROUP BY m.id, m.full_name, m.email, m.voting_power
            ORDER BY participation_rate DESC, m.full_name ASC",
            $params
        );
    }

    /**
     * Rapport des decisions: historique des resolutions votees.
     */
    public function getDecisionsReport(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        return $this->selectAll(
            "SELECT
                mt.id AS meeting_id,
                mt.title AS meeting_title,
                mt.scheduled_at,
                mo.id AS motion_id,
                mo.position,
                mo.title AS motion_title,
                mo.status AS motion_status,
                mo.decision,
                mo.opened_at,
                mo.closed_at,
                COALESCE((mo.results->>'for')::int, 0) AS for_count,
                COALESCE((mo.results->>'against')::int, 0) AS against_count,
                COALESCE((mo.results->>'abstain')::int, 0) AS abstain_count,
                COALESCE((mo.results->>'total_voters')::int, 0) AS total_voters,
                COALESCE((mo.results->>'for_weight')::numeric, 0) AS for_weight,
                COALESCE((mo.results->>'against_weight')::numeric, 0) AS against_weight,
                COALESCE((mo.results->>'abstain_weight')::numeric, 0) AS abstain_weight
            FROM meetings mt
            JOIN motions mo ON mo.meeting_id = mt.id
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              AND mo.status IN ('closed', 'validated')
              {$meetingFilter}
            ORDER BY mt.scheduled_at DESC, mo.position ASC",
            $params
        );
    }

    /**
     * Rapport pouvoir de vote: evolution des tantiemes.
     */
    public function getVotingPowerReport(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        return $this->selectAll(
            "SELECT
                mt.id AS meeting_id,
                mt.title AS meeting_title,
                mt.scheduled_at,
                COUNT(DISTINCT a.member_id) FILTER (WHERE a.mode IN ('present', 'remote')) AS present_count,
                COALESCE(SUM(m.voting_power) FILTER (WHERE a.mode IN ('present', 'remote')), 0) AS present_power,
                (SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL) AS total_power,
                ROUND(
                    COALESCE(SUM(m.voting_power) FILTER (WHERE a.mode IN ('present', 'remote')), 0)::numeric
                    / NULLIF((SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL), 0) * 100,
                    1
                ) AS power_represented_pct
            FROM meetings mt
            LEFT JOIN attendances a ON a.meeting_id = mt.id AND a.mode IN ('present', 'remote', 'proxy')
            LEFT JOIN members m ON m.id = a.member_id
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              {$meetingFilter}
            GROUP BY mt.id, mt.title, mt.scheduled_at
            ORDER BY mt.scheduled_at DESC",
            $params
        );
    }

    /**
     * Rapport procurations: statistiques de delegation.
     */
    public function getProxiesReport(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        return $this->selectAll(
            "SELECT
                mt.id AS meeting_id,
                mt.title AS meeting_title,
                mt.scheduled_at,
                COUNT(DISTINCT p.id) AS proxy_count,
                COUNT(DISTINCT p.giver_member_id) AS unique_givers,
                COUNT(DISTINCT p.receiver_member_id) AS unique_receivers,
                COALESCE(SUM(gm.voting_power), 0) AS delegated_power,
                (SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL) AS total_power,
                ROUND(
                    COALESCE(SUM(gm.voting_power), 0)::numeric
                    / NULLIF((SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL), 0) * 100,
                    1
                ) AS delegated_pct,
                MAX(proxy_counts.max_received) AS max_proxies_per_receiver
            FROM meetings mt
            LEFT JOIN proxies p ON p.meeting_id = mt.id AND p.tenant_id = :tid AND p.revoked_at IS NULL
            LEFT JOIN members gm ON gm.id = p.giver_member_id
            LEFT JOIN (
                SELECT meeting_id, MAX(cnt) AS max_received
                FROM (
                    SELECT meeting_id, receiver_member_id, COUNT(*) AS cnt
                    FROM proxies
                    WHERE tenant_id = :tid AND revoked_at IS NULL
                    GROUP BY meeting_id, receiver_member_id
                ) sub
                GROUP BY meeting_id
            ) proxy_counts ON proxy_counts.meeting_id = mt.id
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              {$meetingFilter}
            GROUP BY mt.id, mt.title, mt.scheduled_at
            ORDER BY mt.scheduled_at DESC",
            $params
        );
    }

    /**
     * Rapport quorum: historique de quorum par seance.
     */
    public function getQuorumReport(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        return $this->selectAll(
            "SELECT
                mt.id AS meeting_id,
                mt.title AS meeting_title,
                mt.scheduled_at,
                mt.status AS meeting_status,
                (SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL) AS total_members,
                COUNT(DISTINCT a.member_id) FILTER (WHERE a.mode IN ('present', 'remote', 'proxy')) AS present_count,
                COALESCE(SUM(m.voting_power) FILTER (WHERE a.mode IN ('present', 'remote', 'proxy')), 0) AS present_power,
                qp.threshold_value AS quorum_threshold,
                qp.unit AS quorum_unit,
                CASE
                    WHEN qp.unit = 'percent' THEN
                        CASE WHEN COUNT(DISTINCT a.member_id) FILTER (WHERE a.mode IN ('present', 'remote', 'proxy'))::numeric
                             / NULLIF((SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL), 0) * 100
                             >= COALESCE(qp.threshold_value, 0)
                        THEN true ELSE false END
                    WHEN qp.unit = 'count' THEN
                        CASE WHEN COUNT(DISTINCT a.member_id) FILTER (WHERE a.mode IN ('present', 'remote', 'proxy'))
                             >= COALESCE(qp.threshold_value, 0)
                        THEN true ELSE false END
                    ELSE true
                END AS quorum_reached
            FROM meetings mt
            LEFT JOIN attendances a ON a.meeting_id = mt.id
            LEFT JOIN members m ON m.id = a.member_id
            LEFT JOIN quorum_policies qp ON qp.id = mt.quorum_policy_id
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              {$meetingFilter}
            GROUP BY mt.id, mt.title, mt.scheduled_at, mt.status, qp.threshold_value, qp.unit
            ORDER BY mt.scheduled_at DESC",
            $params
        );
    }

    /**
     * Resume global pour dashboard.
     */
    public function getSummary(
        string $tenantId,
        ?array $meetingIds = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $meetingFilter = $this->buildMeetingFilter($meetingIds, $fromDate, $toDate, $params);

        $row = $this->selectOne(
            "SELECT
                COUNT(DISTINCT mt.id) AS total_meetings,
                COUNT(DISTINCT mo.id) AS total_motions,
                COUNT(DISTINCT mo.id) FILTER (WHERE mo.decision = 'adopted') AS adopted_count,
                COUNT(DISTINCT mo.id) FILTER (WHERE mo.decision = 'rejected') AS rejected_count,
                COUNT(DISTINCT mo.id) FILTER (WHERE mo.decision IS NULL OR mo.decision NOT IN ('adopted', 'rejected')) AS other_count,
                COALESCE(AVG(
                    (SELECT COUNT(*) FROM attendances WHERE meeting_id = mt.id AND mode IN ('present', 'remote'))
                ), 0)::int AS avg_attendance,
                MIN(mt.scheduled_at) AS first_meeting,
                MAX(mt.scheduled_at) AS last_meeting
            FROM meetings mt
            LEFT JOIN motions mo ON mo.meeting_id = mt.id AND mo.status IN ('closed', 'validated')
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              {$meetingFilter}",
            $params
        );

        return $row ?: [
            'total_meetings' => 0,
            'total_motions' => 0,
            'adopted_count' => 0,
            'rejected_count' => 0,
            'other_count' => 0,
            'avg_attendance' => 0,
            'first_meeting' => null,
            'last_meeting' => null,
        ];
    }

    /**
     * Liste les seances disponibles pour selection.
     */
    public function listAvailableMeetings(
        string $tenantId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $params = [':tid' => $tenantId];
        $dateFilter = '';

        if ($fromDate !== null) {
            $dateFilter .= " AND mt.scheduled_at >= :from_date";
            $params[':from_date'] = $fromDate;
        }

        if ($toDate !== null) {
            $dateFilter .= " AND mt.scheduled_at <= :to_date";
            $params[':to_date'] = $toDate;
        }

        return $this->selectAll(
            "SELECT
                mt.id,
                mt.title,
                mt.scheduled_at,
                mt.status,
                (SELECT COUNT(*) FROM motions WHERE meeting_id = mt.id) AS motion_count,
                (SELECT COUNT(*) FROM attendances WHERE meeting_id = mt.id AND mode IN ('present', 'remote')) AS attendance_count
            FROM meetings mt
            WHERE mt.tenant_id = :tid
              AND mt.status IN ('live', 'closed', 'validated', 'archived')
              {$dateFilter}
            ORDER BY mt.scheduled_at DESC",
            $params
        );
    }

    /**
     * Construit le filtre de seances pour les requetes.
     */
    private function buildMeetingFilter(?array $meetingIds, ?string $fromDate, ?string $toDate, array &$params): string
    {
        $filter = '';

        if (!empty($meetingIds)) {
            $placeholders = [];
            foreach ($meetingIds as $i => $id) {
                $key = ":mid{$i}";
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $filter .= " AND mt.id IN (" . implode(',', $placeholders) . ")";
        }

        if ($fromDate !== null) {
            $filter .= " AND mt.scheduled_at >= :from_date";
            $params[':from_date'] = $fromDate;
        }

        if ($toDate !== null) {
            $filter .= " AND mt.scheduled_at <= :to_date";
            $params[':to_date'] = $toDate;
        }

        return $filter;
    }
}
