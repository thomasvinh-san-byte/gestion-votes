<?php
declare(strict_types=1);

/**
 * meeting_summary.php - Résumé statistique d'une séance
 *
 * GET /api/v1/meeting_summary.php?meeting_id={uuid}
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['operator', 'president', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();
$repo = new MeetingRepository();

// Vérifier que la séance existe
$meeting = $repo->findSummaryFields($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

// Total membres actifs
$totalMembers = $repo->countActiveMembers($tenantId);

// Présents
$presentCount = $repo->countPresent($meetingId);

// Représentés (proxy)
$proxyCount = $repo->countProxy($meetingId);

// Absents
$absentCount = $totalMembers - $presentCount - $proxyCount;

// Résolutions
$motionsCount = $repo->countMotions($meetingId);
$closedMotionsCount = $repo->countClosedMotions($meetingId);
$openMotionsCount = $repo->countOpenMotions($meetingId);

// Adoptées / Rejetées
$adoptedCount = $repo->countAdoptedMotions($meetingId);
$rejectedCount = $repo->countRejectedMotions($meetingId);

// Bulletins de vote
$ballotsCount = $repo->countBallots($meetingId);

// Poids total voté
$totalVotedWeight = $repo->sumBallotWeight($meetingId);

// Procurations
$proxiesCount = $repo->countProxies($meetingId);

// Incidents (comptés via audit_events)
$incidentsCount = $repo->countIncidents($meetingId);

// Votes manuels
$manualVotesCount = $repo->countManualVotes($meetingId);

api_ok([
    'meeting_id' => $meetingId,
    'meeting_title' => $meeting['title'],
    'status' => $meeting['status'],
    'validated_at' => $meeting['validated_at'],
    'president_name' => $meeting['president_name'],
    'data' => [
        'total_members' => $totalMembers,
        'present_count' => $presentCount,
        'proxy_count' => $proxyCount,
        'absent_count' => $absentCount,
        'motions_count' => $motionsCount,
        'closed_motions_count' => $closedMotionsCount,
        'open_motions_count' => $openMotionsCount,
        'adopted_count' => $adoptedCount,
        'rejected_count' => $rejectedCount,
        'ballots_count' => $ballotsCount,
        'total_voted_weight' => round($totalVotedWeight, 2),
        'proxies_count' => $proxiesCount,
        'incidents_count' => $incidentsCount,
        'manual_votes_count' => $manualVotesCount,
    ],
]);
