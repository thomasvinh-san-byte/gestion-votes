<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les statistiques et analytics.
 *
 * Centralise toutes les requetes d'agregation pour le dashboard analytics.
 * Aucune logique metier — uniquement des requetes de lecture.
 */
class AnalyticsRepository extends AbstractRepository
{
    // =========================================================================
    // VUE D'ENSEMBLE (OVERVIEW)
    // =========================================================================

    /**
     * Compte le nombre total de seances pour un tenant.
     */
    public function countMeetings(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Compte le nombre total de motions pour un tenant.
     */
    public function countMotions(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Compte le nombre total de bulletins pour un tenant.
     */
    public function countBallots(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Repartition des seances par statut.
     */
    public function getMeetingsByStatus(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT status::text as status, COUNT(*) as count
             FROM meetings WHERE tenant_id = :tid
             GROUP BY status",
            [':tid' => $tenantId]
        );
    }

    /**
     * Repartition des motions par decision.
     */
    public function getMotionDecisions(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT COALESCE(decision, 'pending') as decision, COUNT(*) as count
             FROM motions WHERE tenant_id = :tid
             GROUP BY decision",
            [':tid' => $tenantId]
        );
    }

    /**
     * Taux de participation moyen sur la derniere annee.
     */
    public function getAverageParticipationRate(string $tenantId): float
    {
        $result = $this->scalar(
            "SELECT
                AVG(CASE WHEN eligible > 0 THEN present::float / eligible * 100 ELSE 0 END) as avg_rate
             FROM (
                SELECT
                    a.meeting_id,
                    COUNT(CASE WHEN a.mode IN ('present', 'remote') THEN 1 END) as present,
                    (SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true) as eligible
                FROM attendances a
                JOIN meetings m ON m.id = a.meeting_id
                WHERE m.tenant_id = :tid2
                  AND m.started_at > NOW() - INTERVAL '1 year'
                GROUP BY a.meeting_id
             ) sub",
            [':tid' => $tenantId, ':tid2' => $tenantId]
        );
        return round((float)($result ?? 0), 1);
    }

    // =========================================================================
    // PARTICIPATION
    // =========================================================================

    /**
     * Statistiques de participation par seance.
     */
    public function getParticipationByMeeting(string $tenantId, string $dateFrom, int $limit): array
    {
        return $this->selectAll(
            "SELECT
                m.id,
                m.title,
                m.started_at,
                COUNT(CASE WHEN a.mode IN ('present', 'remote') THEN 1 END) as present_count,
                COUNT(CASE WHEN a.mode = 'proxy' THEN 1 END) as proxy_count,
                COUNT(a.id) as total_attendees
             FROM meetings m
             LEFT JOIN attendances a ON a.meeting_id = m.id
             WHERE m.tenant_id = :tid
               AND m.started_at IS NOT NULL
               AND m.started_at >= :from
             GROUP BY m.id, m.title, m.started_at
             ORDER BY m.started_at DESC
             LIMIT :lim",
            [':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]
        );
    }

    // =========================================================================
    // MOTIONS
    // =========================================================================

    /**
     * Statistiques des motions par seance.
     */
    public function getMotionsStatsByMeeting(string $tenantId, string $dateFrom, int $limit): array
    {
        return $this->selectAll(
            "SELECT
                m.id as meeting_id,
                m.title as meeting_title,
                m.started_at,
                COUNT(mo.id) as total_motions,
                COUNT(CASE WHEN mo.decision = 'adopted' THEN 1 END) as adopted,
                COUNT(CASE WHEN mo.decision = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN mo.decision IS NULL OR mo.decision = '' THEN 1 END) as pending
             FROM meetings m
             LEFT JOIN motions mo ON mo.meeting_id = m.id
             WHERE m.tenant_id = :tid
               AND m.started_at IS NOT NULL
               AND m.started_at >= :from
             GROUP BY m.id, m.title, m.started_at
             HAVING COUNT(mo.id) > 0
             ORDER BY m.started_at DESC
             LIMIT :lim",
            [':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]
        );
    }

    /**
     * Totaux des motions (adopted/rejected).
     */
    public function getMotionsTotals(string $tenantId, string $dateFrom): array
    {
        $row = $this->selectOne(
            "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN decision = 'adopted' THEN 1 END) as adopted,
                COUNT(CASE WHEN decision = 'rejected' THEN 1 END) as rejected
             FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND m.started_at >= :from",
            [':tid' => $tenantId, ':from' => $dateFrom]
        );
        return $row ?? ['total' => 0, 'adopted' => 0, 'rejected' => 0];
    }

    // =========================================================================
    // DUREE DES VOTES
    // =========================================================================

    /**
     * Duree des votes (motions avec opened_at et closed_at).
     */
    public function getVoteDurations(string $tenantId, string $dateFrom, int $limit): array
    {
        return $this->selectAll(
            "SELECT
                mo.id,
                mo.title,
                mo.opened_at,
                mo.closed_at,
                EXTRACT(EPOCH FROM (mo.closed_at - mo.opened_at)) as duration_seconds,
                m.title as meeting_title
             FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND mo.opened_at IS NOT NULL
               AND mo.closed_at IS NOT NULL
               AND mo.opened_at >= :from
             ORDER BY mo.closed_at DESC
             LIMIT :lim",
            [':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]
        );
    }

    // =========================================================================
    // PROCURATIONS
    // =========================================================================

    /**
     * Statistiques des procurations par seance.
     */
    public function getProxiesStatsByMeeting(string $tenantId, string $dateFrom, int $limit): array
    {
        return $this->selectAll(
            "SELECT
                m.id as meeting_id,
                m.title,
                m.started_at,
                COUNT(p.id) as proxy_count,
                COUNT(DISTINCT p.receiver_member_id) as distinct_receivers,
                MAX(receiver_counts.count) as max_per_receiver
             FROM meetings m
             LEFT JOIN proxies p ON p.meeting_id = m.id AND p.revoked_at IS NULL
             LEFT JOIN (
                SELECT meeting_id, receiver_member_id, COUNT(*) as count
                FROM proxies
                WHERE revoked_at IS NULL
                GROUP BY meeting_id, receiver_member_id
             ) receiver_counts ON receiver_counts.meeting_id = m.id
             WHERE m.tenant_id = :tid
               AND m.started_at IS NOT NULL
               AND m.started_at >= :from
             GROUP BY m.id, m.title, m.started_at
             ORDER BY m.started_at DESC
             LIMIT :lim",
            [':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]
        );
    }

    /**
     * Total des procurations.
     */
    public function countProxies(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies p
             JOIN meetings m ON m.id = p.meeting_id
             WHERE m.tenant_id = :tid AND m.started_at >= :from",
            [':tid' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    // =========================================================================
    // ANOMALIES
    // =========================================================================

    /**
     * Compte les seances avec participation faible (<50%).
     */
    public function countLowParticipationMeetings(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM (
                SELECT m.id,
                    COUNT(CASE WHEN a.mode IN ('present', 'remote', 'proxy') THEN 1 END) as attended,
                    (SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true) as eligible
                FROM meetings m
                LEFT JOIN attendances a ON a.meeting_id = m.id
                WHERE m.tenant_id = :tid2
                  AND m.started_at IS NOT NULL
                  AND m.started_at >= :from
                GROUP BY m.id
                HAVING eligible > 0 AND (attended::float / eligible) < 0.5
            ) sub",
            [':tid' => $tenantId, ':tid2' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    /**
     * Compte les seances avec problemes de quorum.
     */
    public function countQuorumIssues(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(DISTINCT m.id) FROM meetings m
             JOIN motions mo ON mo.meeting_id = m.id
             WHERE m.tenant_id = :tid
               AND m.started_at >= :from
               AND mo.decision IS NOT NULL
               AND mo.quorum_reached = false",
            [':tid' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    /**
     * Compte les votes incomplets (ouverts mais jamais fermes).
     */
    public function countIncompleteVotes(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND mo.opened_at IS NOT NULL
               AND mo.closed_at IS NULL
               AND mo.opened_at >= :from",
            [':tid' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    /**
     * Compte les membres avec forte concentration de procurations (>3).
     */
    public function countHighProxyConcentration(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM (
                SELECT p.receiver_member_id, COUNT(*) as proxy_count
                FROM proxies p
                JOIN meetings m ON m.id = p.meeting_id
                WHERE m.tenant_id = :tid
                  AND m.started_at >= :from
                  AND p.revoked_at IS NULL
                GROUP BY p.receiver_member_id
                HAVING COUNT(*) > 3
            ) sub",
            [':tid' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    /**
     * Calcule le taux d'abstention.
     */
    public function getAbstentionRate(string $tenantId, string $dateFrom): float
    {
        $result = $this->scalar(
            "SELECT
                CASE WHEN COUNT(*) > 0
                    THEN ROUND(COUNT(CASE WHEN b.value = 'abstain' THEN 1 END)::numeric / COUNT(*) * 100, 1)
                    ELSE 0
                END as rate
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND b.created_at >= :from",
            [':tid' => $tenantId, ':from' => $dateFrom]
        );
        return (float)($result ?? 0);
    }

    /**
     * Compte les votes tres courts (<30 secondes).
     */
    public function countVeryShortVotes(string $tenantId, string $dateFrom): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND mo.opened_at IS NOT NULL
               AND mo.closed_at IS NOT NULL
               AND mo.opened_at >= :from
               AND EXTRACT(EPOCH FROM (mo.closed_at - mo.opened_at)) < 30",
            [':tid' => $tenantId, ':from' => $dateFrom]
        ) ?? 0);
    }

    /**
     * Liste des seances avec flags d'anomalies.
     */
    public function getFlaggedMeetings(string $tenantId, string $dateFrom, int $limit): array
    {
        return $this->selectAll(
            "SELECT
                m.id,
                m.title,
                m.started_at as date,
                COUNT(CASE WHEN a.mode IN ('present', 'remote', 'proxy') THEN 1 END) as attended,
                (SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true) as eligible,
                COUNT(DISTINCT CASE WHEN mo.decision IS NOT NULL AND mo.quorum_reached = false THEN mo.id END) as quorum_issues,
                COUNT(DISTINCT CASE WHEN mo.opened_at IS NOT NULL AND mo.closed_at IS NULL THEN mo.id END) as incomplete
             FROM meetings m
             LEFT JOIN attendances a ON a.meeting_id = m.id
             LEFT JOIN motions mo ON mo.meeting_id = m.id
             WHERE m.tenant_id = :tid2
               AND m.started_at IS NOT NULL
               AND m.started_at >= :from
             GROUP BY m.id, m.title, m.started_at
             ORDER BY m.started_at DESC
             LIMIT :lim",
            [':tid' => $tenantId, ':tid2' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]
        );
    }

    // =========================================================================
    // DISTRIBUTION TEMPS DE VOTE
    // =========================================================================

    /**
     * Distribution des temps de reponse (delai ouverture -> vote).
     */
    public function getVoteTimingDistribution(string $tenantId, string $dateFrom): array
    {
        return $this->selectAll(
            "SELECT
                EXTRACT(EPOCH FROM (b.created_at - mo.opened_at)) as response_seconds
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE m.tenant_id = :tid
               AND mo.opened_at IS NOT NULL
               AND b.created_at >= :from
               AND b.created_at >= mo.opened_at",
            [':tid' => $tenantId, ':from' => $dateFrom]
        );
    }
}
