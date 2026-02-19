<?php
// public/api/v1/analytics.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MemberRepository;
use AgVote\Repository\AnalyticsRepository;

api_require_role('operator');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$tenantId = api_current_tenant_id();
$type = trim($_GET['type'] ?? 'overview');
$period = trim($_GET['period'] ?? 'year');
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

$memberRepo = new MemberRepository();
$analyticsRepo = new AnalyticsRepository();

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
        'overview' => getOverview($tenantId, $memberRepo, $analyticsRepo),
        'participation' => getParticipation($tenantId, $dateFrom, $memberRepo, $analyticsRepo, $limit),
        'motions' => getMotionsStats($tenantId, $dateFrom, $analyticsRepo, $limit),
        'vote_duration' => getVoteDuration($tenantId, $dateFrom, $analyticsRepo, $limit),
        'proxies' => getProxiesStats($tenantId, $dateFrom, $analyticsRepo, $limit),
        'anomalies' => getAnomalies($tenantId, $dateFrom, $analyticsRepo, $limit),
        'vote_timing' => getVoteTimingDistribution($tenantId, $dateFrom, $analyticsRepo),
        default => api_fail('invalid_type', 400),
    };

    api_ok($data);
} catch (Throwable $e) {
    error_log('Error in analytics.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}

/**
 * Vue d'ensemble globale
 */
function getOverview(
    string $tenantId,
    MemberRepository $memberRepo,
    AnalyticsRepository $analyticsRepo
): array {
    $totalMeetings = $analyticsRepo->countMeetings($tenantId);
    $totalMembers = $memberRepo->countActiveNotDeleted($tenantId);
    $totalMotions = $analyticsRepo->countMotions($tenantId);
    $totalBallots = $analyticsRepo->countBallots($tenantId);

    // Seances par statut
    $statusRows = $analyticsRepo->getMeetingsByStatus($tenantId);
    $meetingsByStatus = [];
    foreach ($statusRows as $row) {
        $meetingsByStatus[$row['status']] = (int)$row['count'];
    }

    // Motions adoptees vs rejetees
    $decisionRows = $analyticsRepo->getMotionDecisions($tenantId);
    $motionDecisions = [];
    foreach ($decisionRows as $row) {
        $motionDecisions[$row['decision']] = (int)$row['count'];
    }

    // Participation moyenne
    $avgParticipation = $analyticsRepo->getAverageParticipationRate($tenantId);

    return [
        'totals' => [
            'meetings' => $totalMeetings,
            'members' => $totalMembers,
            'motions' => $totalMotions,
            'ballots' => $totalBallots,
        ],
        'meetings_by_status' => $meetingsByStatus,
        'motion_decisions' => $motionDecisions,
        'avg_participation_rate' => $avgParticipation,
    ];
}

/**
 * Statistiques de participation par seance
 */
function getParticipation(
    string $tenantId,
    string $dateFrom,
    MemberRepository $memberRepo,
    AnalyticsRepository $analyticsRepo,
    int $limit
): array {
    $meetings = $analyticsRepo->getParticipationByMeeting($tenantId, $dateFrom, $limit);
    $eligibleCount = $memberRepo->countActiveNotDeleted($tenantId);

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

    return [
        'eligible_count' => $eligibleCount,
        'meetings' => array_reverse($participation),
    ];
}

/**
 * Statistiques des motions (adoptees/rejetees)
 */
function getMotionsStats(
    string $tenantId,
    string $dateFrom,
    AnalyticsRepository $analyticsRepo,
    int $limit
): array {
    $byMeeting = $analyticsRepo->getMotionsStatsByMeeting($tenantId, $dateFrom, $limit);
    $summary = $analyticsRepo->getMotionsTotals($tenantId, $dateFrom);

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
 * Duree moyenne des votes
 */
function getVoteDuration(
    string $tenantId,
    string $dateFrom,
    AnalyticsRepository $analyticsRepo,
    int $limit
): array {
    $motions = $analyticsRepo->getVoteDurations($tenantId, $dateFrom, $limit);

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
    AnalyticsRepository $analyticsRepo,
    int $limit
): array {
    $byMeeting = $analyticsRepo->getProxiesStatsByMeeting($tenantId, $dateFrom, $limit);
    $totalCount = $analyticsRepo->countProxies($tenantId, $dateFrom);

    return [
        'total_proxies' => $totalCount,
        'by_meeting' => array_reverse($byMeeting),
    ];
}

/**
 * Detection d'anomalies (statistiques agregees, RGPD-compliant)
 *
 * Ne revele JAMAIS l'identite des membres individuels.
 * Signale uniquement des indicateurs agreges pour ameliorer la qualite des processus.
 */
function getAnomalies(
    string $tenantId,
    string $dateFrom,
    AnalyticsRepository $analyticsRepo,
    int $limit
): array {
    $lowParticipationCount = $analyticsRepo->countLowParticipationMeetings($tenantId, $dateFrom);
    $quorumIssuesCount = $analyticsRepo->countQuorumIssues($tenantId, $dateFrom);
    $incompleteVotesCount = $analyticsRepo->countIncompleteVotes($tenantId, $dateFrom);
    $highProxyConcentrationCount = $analyticsRepo->countHighProxyConcentration($tenantId, $dateFrom);
    $abstentionRateValue = $analyticsRepo->getAbstentionRate($tenantId, $dateFrom);
    $veryShortVotesCount = $analyticsRepo->countVeryShortVotes($tenantId, $dateFrom);

    $meetings = $analyticsRepo->getFlaggedMeetings($tenantId, $dateFrom, $limit);

    // Construire la liste des seances avec flags
    $flaggedList = [];
    foreach ($meetings as $m) {
        $flags = [];
        $eligible = (int)$m['eligible'];
        $attended = (int)$m['attended'];
        $rate = $eligible > 0 ? round($attended / $eligible * 100, 1) : 0;

        if ($rate < 50 && $eligible > 0) {
            $flags[] = 'Participation faible';
        }
        if ((int)$m['quorum_issues'] > 0) {
            $flags[] = 'Quorum';
        }
        if ((int)$m['incomplete'] > 0) {
            $flags[] = 'Votes incomplets';
        }

        if (count($flags) > 0) {
            $flaggedList[] = [
                'meeting_id' => $m['id'],
                'title' => $m['title'],
                'date' => $m['date'],
                'participation_rate' => $rate,
                'flags' => $flags,
            ];
        }
    }

    return [
        'indicators' => [
            'low_participation_count' => $lowParticipationCount,
            'quorum_issues_count' => $quorumIssuesCount,
            'incomplete_votes_count' => $incompleteVotesCount,
            'high_proxy_concentration' => $highProxyConcentrationCount,
            'abstention_rate' => $abstentionRateValue,
            'very_short_votes_count' => $veryShortVotesCount,
        ],
        'flagged_meetings' => $flaggedList,
    ];
}

/**
 * Distribution des temps de reponse (delai entre ouverture et vote)
 */
function getVoteTimingDistribution(
    string $tenantId,
    string $dateFrom,
    AnalyticsRepository $analyticsRepo
): array {
    $timesRows = $analyticsRepo->getVoteTimingDistribution($tenantId, $dateFrom);

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
    $count = 0;
    foreach ($timesRows as $row) {
        $s = (float)$row['response_seconds'];
        $totalSeconds += $s;
        $count++;
        if ($s < 10) $distribution['0-10s']++;
        elseif ($s < 30) $distribution['10-30s']++;
        elseif ($s < 60) $distribution['30s-1m']++;
        elseif ($s < 120) $distribution['1-2m']++;
        elseif ($s < 300) $distribution['2-5m']++;
        else $distribution['5m+']++;
    }

    $avgSeconds = $count > 0 ? $totalSeconds / $count : 0;

    return [
        'count' => $count,
        'avg_seconds' => round($avgSeconds, 1),
        'avg_formatted' => formatDuration($avgSeconds),
        'distribution' => $distribution,
    ];
}

/**
 * Formate une duree en secondes
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
