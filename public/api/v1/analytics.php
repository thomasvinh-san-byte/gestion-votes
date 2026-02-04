<?php
// public/api/v1/analytics.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;

api_require_role('operator');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$tenantId = api_current_tenant_id();
$type = trim($_GET['type'] ?? 'overview');
$period = trim($_GET['period'] ?? 'year');
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

$meetingRepo = new MeetingRepository();
$memberRepo = new MemberRepository();
$attRepo = new AttendanceRepository();
$motionRepo = new MotionRepository();
$ballotRepo = new BallotRepository();

// Determine date range based on period
$dateFrom = match($period) {
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year')),
    'all' => '2000-01-01',
    default => date('Y-m-d', strtotime('-1 year')),
};

try {
    $data = match($type) {
        'overview' => getOverview($tenantId, $meetingRepo, $memberRepo, $motionRepo, $ballotRepo),
        'participation' => getParticipation($tenantId, $dateFrom, $meetingRepo, $attRepo, $memberRepo, $limit),
        'motions' => getMotionsStats($tenantId, $dateFrom, $meetingRepo, $motionRepo, $limit),
        'vote_duration' => getVoteDuration($tenantId, $dateFrom, $motionRepo, $limit),
        'proxies' => getProxiesStats($tenantId, $dateFrom, $meetingRepo, $limit),
        'top_voters' => getTopVoters($tenantId, $dateFrom, $ballotRepo, $limit),
        'vote_timing' => getVoteTimingDistribution($tenantId, $dateFrom, $ballotRepo),
        default => api_fail('invalid_type', 400),
    };

    api_ok($data);
} catch (PDOException $e) {
    error_log("Analytics API error: " . $e->getMessage());
    api_fail('database_error', 500);
}

/**
 * Vue d'ensemble globale
 */
function getOverview(
    string $tenantId,
    MeetingRepository $meetingRepo,
    MemberRepository $memberRepo,
    MotionRepository $motionRepo,
    BallotRepository $ballotRepo
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    // Compteurs globaux
    $totalMeetings = (int)($db->query(
        "SELECT COUNT(*) FROM meetings WHERE tenant_id = " . $db->quote($tenantId)
    )->fetchColumn() ?? 0);

    $totalMembers = $memberRepo->countNotDeleted($tenantId);
    $totalMotions = (int)($db->query(
        "SELECT COUNT(*) FROM motions WHERE tenant_id = " . $db->quote($tenantId)
    )->fetchColumn() ?? 0);

    $totalBallots = (int)($db->query(
        "SELECT COUNT(*) FROM ballots b
         JOIN motions m ON m.id = b.motion_id
         WHERE m.tenant_id = " . $db->quote($tenantId)
    )->fetchColumn() ?? 0);

    // Séances par statut
    $meetingsByStatus = $db->query(
        "SELECT status::text, COUNT(*) as count
         FROM meetings WHERE tenant_id = " . $db->quote($tenantId) . "
         GROUP BY status"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);

    // Motions adoptées vs rejetées
    $motionDecisions = $db->query(
        "SELECT
            COALESCE(decision, 'pending') as decision,
            COUNT(*) as count
         FROM motions WHERE tenant_id = " . $db->quote($tenantId) . "
         GROUP BY decision"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);

    // Participation moyenne (dernière année)
    $avgParticipation = $db->query(
        "SELECT
            AVG(CASE WHEN eligible > 0 THEN present::float / eligible * 100 ELSE 0 END) as avg_rate
         FROM (
            SELECT
                a.meeting_id,
                COUNT(CASE WHEN a.mode IN ('present', 'remote') THEN 1 END) as present,
                (SELECT COUNT(*) FROM members WHERE tenant_id = " . $db->quote($tenantId) . " AND is_active = true) as eligible
            FROM attendances a
            JOIN meetings m ON m.id = a.meeting_id
            WHERE m.tenant_id = " . $db->quote($tenantId) . "
              AND m.started_at > NOW() - INTERVAL '1 year'
            GROUP BY a.meeting_id
         ) sub"
    )->fetchColumn();

    return [
        'totals' => [
            'meetings' => $totalMeetings,
            'members' => $totalMembers,
            'motions' => $totalMotions,
            'ballots' => $totalBallots,
        ],
        'meetings_by_status' => $meetingsByStatus,
        'motion_decisions' => $motionDecisions,
        'avg_participation_rate' => round((float)($avgParticipation ?? 0), 1),
    ];
}

/**
 * Statistiques de participation par séance
 */
function getParticipation(
    string $tenantId,
    string $dateFrom,
    MeetingRepository $meetingRepo,
    AttendanceRepository $attRepo,
    MemberRepository $memberRepo,
    int $limit
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    $stmt = $db->prepare(
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
         LIMIT :lim"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]);
    $meetings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $eligibleCount = $memberRepo->countNotDeleted($tenantId);

    $participation = [];
    foreach ($meetings as $m) {
        $rate = $eligibleCount > 0
            ? round(((int)$m['present_count'] + (int)$m['proxy_count']) / $eligibleCount * 100, 1)
            : 0;

        $participation[] = [
            'meeting_id' => $m['id'],
            'title' => $m['title'],
            'date' => $m['started_at'],
            'present' => (int)$m['present_count'],
            'proxy' => (int)$m['proxy_count'],
            'total' => (int)$m['total_attendees'],
            'eligible' => $eligibleCount,
            'rate' => $rate,
        ];
    }

    // Inverser pour avoir l'ordre chronologique
    return [
        'eligible_count' => $eligibleCount,
        'meetings' => array_reverse($participation),
    ];
}

/**
 * Statistiques des motions (adoptées/rejetées)
 */
function getMotionsStats(
    string $tenantId,
    string $dateFrom,
    MeetingRepository $meetingRepo,
    MotionRepository $motionRepo,
    int $limit
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    // Stats par séance
    $stmt = $db->prepare(
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
         LIMIT :lim"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]);
    $byMeeting = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Totaux
    $totals = $db->prepare(
        "SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN decision = 'adopted' THEN 1 END) as adopted,
            COUNT(CASE WHEN decision = 'rejected' THEN 1 END) as rejected
         FROM motions mo
         JOIN meetings m ON m.id = mo.meeting_id
         WHERE m.tenant_id = :tid
           AND m.started_at >= :from"
    );
    $totals->execute([':tid' => $tenantId, ':from' => $dateFrom]);
    $summary = $totals->fetch(\PDO::FETCH_ASSOC);

    return [
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'adopted' => (int)($summary['adopted'] ?? 0),
            'rejected' => (int)($summary['rejected'] ?? 0),
            'adoption_rate' => (int)($summary['total'] ?? 0) > 0
                ? round((int)($summary['adopted'] ?? 0) / (int)$summary['total'] * 100, 1)
                : 0,
        ],
        'by_meeting' => array_reverse($byMeeting),
    ];
}

/**
 * Durée moyenne des votes
 */
function getVoteDuration(
    string $tenantId,
    string $dateFrom,
    MotionRepository $motionRepo,
    int $limit
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    $stmt = $db->prepare(
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
         LIMIT :lim"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]);
    $motions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $durations = [];
    $totalSeconds = 0;
    foreach ($motions as $mo) {
        $seconds = (float)$mo['duration_seconds'];
        $totalSeconds += $seconds;
        $durations[] = [
            'motion_id' => $mo['id'],
            'title' => $mo['title'],
            'meeting_title' => $mo['meeting_title'],
            'opened_at' => $mo['opened_at'],
            'closed_at' => $mo['closed_at'],
            'duration_seconds' => round($seconds),
            'duration_formatted' => formatDuration($seconds),
        ];
    }

    $count = count($durations);
    $avgSeconds = $count > 0 ? $totalSeconds / $count : 0;

    // Distribution par tranches
    $distribution = [
        '0-30s' => 0,
        '30s-1m' => 0,
        '1-2m' => 0,
        '2-5m' => 0,
        '5-10m' => 0,
        '10m+' => 0,
    ];
    foreach ($motions as $mo) {
        $s = (float)$mo['duration_seconds'];
        if ($s < 30) $distribution['0-30s']++;
        elseif ($s < 60) $distribution['30s-1m']++;
        elseif ($s < 120) $distribution['1-2m']++;
        elseif ($s < 300) $distribution['2-5m']++;
        elseif ($s < 600) $distribution['5-10m']++;
        else $distribution['10m+']++;
    }

    return [
        'count' => $count,
        'avg_seconds' => round($avgSeconds),
        'avg_formatted' => formatDuration($avgSeconds),
        'distribution' => $distribution,
        'motions' => array_reverse($durations),
    ];
}

/**
 * Statistiques des procurations
 */
function getProxiesStats(
    string $tenantId,
    string $dateFrom,
    MeetingRepository $meetingRepo,
    int $limit
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    $stmt = $db->prepare(
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
         LIMIT :lim"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]);
    $byMeeting = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Total
    $total = $db->prepare(
        "SELECT COUNT(*) FROM proxies p
         JOIN meetings m ON m.id = p.meeting_id
         WHERE m.tenant_id = :tid AND m.started_at >= :from"
    );
    $total->execute([':tid' => $tenantId, ':from' => $dateFrom]);
    $totalCount = (int)$total->fetchColumn();

    return [
        'total_proxies' => $totalCount,
        'by_meeting' => array_reverse($byMeeting),
    ];
}

/**
 * Top votants (membres les plus actifs)
 */
function getTopVoters(
    string $tenantId,
    string $dateFrom,
    BallotRepository $ballotRepo,
    int $limit
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    $stmt = $db->prepare(
        "SELECT
            mb.id as member_id,
            mb.full_name,
            COUNT(b.id) as ballot_count,
            COUNT(DISTINCT mo.meeting_id) as meetings_voted,
            SUM(b.weight) as total_weight
         FROM ballots b
         JOIN members mb ON mb.id = b.member_id
         JOIN motions mo ON mo.id = b.motion_id
         JOIN meetings m ON m.id = mo.meeting_id
         WHERE m.tenant_id = :tid
           AND b.created_at >= :from
         GROUP BY mb.id, mb.full_name
         ORDER BY ballot_count DESC
         LIMIT :lim"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom, ':lim' => $limit]);
    $topVoters = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return [
        'voters' => $topVoters,
    ];
}

/**
 * Distribution des temps de réponse (délai entre ouverture et vote)
 */
function getVoteTimingDistribution(
    string $tenantId,
    string $dateFrom,
    BallotRepository $ballotRepo
): array {
    $db = \AgVote\Database\Connection::getInstance()->getPdo();

    $stmt = $db->prepare(
        "SELECT
            EXTRACT(EPOCH FROM (b.created_at - mo.opened_at)) as response_seconds
         FROM ballots b
         JOIN motions mo ON mo.id = b.motion_id
         JOIN meetings m ON m.id = mo.meeting_id
         WHERE m.tenant_id = :tid
           AND mo.opened_at IS NOT NULL
           AND b.created_at >= :from
           AND b.created_at >= mo.opened_at"
    );
    $stmt->execute([':tid' => $tenantId, ':from' => $dateFrom]);
    $times = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    // Distribution par tranches
    $distribution = [
        '0-10s' => 0,
        '10-30s' => 0,
        '30s-1m' => 0,
        '1-2m' => 0,
        '2-5m' => 0,
        '5m+' => 0,
    ];

    $totalSeconds = 0;
    foreach ($times as $s) {
        $s = (float)$s;
        $totalSeconds += $s;
        if ($s < 10) $distribution['0-10s']++;
        elseif ($s < 30) $distribution['10-30s']++;
        elseif ($s < 60) $distribution['30s-1m']++;
        elseif ($s < 120) $distribution['1-2m']++;
        elseif ($s < 300) $distribution['2-5m']++;
        else $distribution['5m+']++;
    }

    $count = count($times);
    $avgSeconds = $count > 0 ? $totalSeconds / $count : 0;

    return [
        'count' => $count,
        'avg_seconds' => round($avgSeconds, 1),
        'avg_formatted' => formatDuration($avgSeconds),
        'distribution' => $distribution,
    ];
}

/**
 * Formate une durée en secondes
 */
function formatDuration(float $seconds): string {
    if ($seconds < 60) {
        return round($seconds) . 's';
    } elseif ($seconds < 3600) {
        $m = floor($seconds / 60);
        $s = round($seconds % 60);
        return $m . 'm' . ($s > 0 ? ' ' . $s . 's' : '');
    } else {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
    }
}
