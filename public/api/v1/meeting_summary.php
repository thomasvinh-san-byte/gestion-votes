<?php
declare(strict_types=1);

/**
 * meeting_summary.php - Résumé statistique d'une séance
 * 
 * GET /api/v1/meeting_summary.php?meeting_id={uuid}
 * 
 * Retourne un résumé pour la validation :
 * - Nombre de membres
 * - Présents
 * - Résolutions
 * - Votes
 */

require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'president', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe
$meeting = db_one("
    SELECT id, title, status, president_name, validated_at
    FROM meetings 
    WHERE tenant_id = ? AND id = ?
", [$tenantId, $meetingId]);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

// Total membres actifs
$totalMembers = (int)db_scalar("
    SELECT COUNT(*) FROM members 
    WHERE tenant_id = ? AND is_active = true
", [$tenantId]);

// Présents
$presentCount = (int)db_scalar("
    SELECT COUNT(*) FROM attendances
    WHERE meeting_id = ? AND mode IN ('present', 'remote')
", [$meetingId]);

// Représentés (proxy)
$proxyCount = (int)db_scalar("
    SELECT COUNT(*) FROM attendances
    WHERE meeting_id = ? AND mode = 'proxy'
", [$meetingId]);

// Absents
$absentCount = $totalMembers - $presentCount - $proxyCount;

// Résolutions
$motionsCount = (int)db_scalar("
    SELECT COUNT(*) FROM motions WHERE meeting_id = ?
", [$meetingId]);

$closedMotionsCount = (int)db_scalar("
    SELECT COUNT(*) FROM motions 
    WHERE meeting_id = ? AND closed_at IS NOT NULL
", [$meetingId]);

$openMotionsCount = (int)db_scalar("
    SELECT COUNT(*) FROM motions 
    WHERE meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL
", [$meetingId]);

// Adoptées / Rejetées
$adoptedCount = (int)db_scalar("
    SELECT COUNT(*) FROM motions 
    WHERE meeting_id = ? AND decision = 'adopted'
", [$meetingId]);

$rejectedCount = (int)db_scalar("
    SELECT COUNT(*) FROM motions 
    WHERE meeting_id = ? AND decision = 'rejected'
", [$meetingId]);

// Bulletins de vote
$ballotsCount = (int)db_scalar("
    SELECT COUNT(*) FROM ballots b
    JOIN motions m ON m.id = b.motion_id
    WHERE m.meeting_id = ?
", [$meetingId]);

// Poids total voté
$totalVotedWeight = (float)db_scalar("
    SELECT COALESCE(SUM(b.weight), 0) FROM ballots b
    JOIN motions m ON m.id = b.motion_id
    WHERE m.meeting_id = ?
", [$meetingId]) ?: 0;

// Procurations
$proxiesCount = (int)db_scalar("
    SELECT COUNT(*) FROM proxies WHERE meeting_id = ?
", [$meetingId]);

// Incidents
$incidentsCount = 0;
try {
    $incidentsCount = (int)db_scalar("
        SELECT COUNT(*) FROM vote_incidents WHERE meeting_id = ?
    ", [$meetingId]) ?: 0;
} catch (\Throwable $e) {
    // Table peut ne pas exister
}

// Votes manuels
$manualVotesCount = (int)db_scalar("
    SELECT COUNT(*) FROM ballots b
    JOIN motions m ON m.id = b.motion_id
    WHERE m.meeting_id = ? AND b.source = 'manual'
", [$meetingId]);

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
