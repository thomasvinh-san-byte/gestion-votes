<?php
declare(strict_types=1);

/**
 * Statistiques des invitations pour une seance.
 *
 * GET /invitations_stats.php?meeting_id=X
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\InvitationRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\EmailEventRepository;

api_require_role(['operator', 'admin', 'auditor']);
$input = api_request('GET');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

// Verifier que la seance existe
api_guard_meeting_exists($meetingId);

$invitationRepo = new InvitationRepository();
$queueRepo = new EmailQueueRepository();
$eventRepo = new EmailEventRepository();

// Statistiques des invitations
$invitationStats = $invitationRepo->getStatsForMeeting($meetingId, $tenantId);

// Statistiques de la file d'attente
$queueStats = $queueRepo->countByStatusForMeeting($meetingId);
$queueByStatus = [];
foreach ($queueStats as $row) {
    $queueByStatus[$row['status']] = (int)$row['count'];
}

// Evenements recents pour cette seance
$recentEvents = $eventRepo->countByTypeForMeeting($meetingId);
$eventsByType = [];
foreach ($recentEvents as $row) {
    $eventsByType[$row['event_type']] = (int)$row['count'];
}

// Calculer les taux
$total = (int)$invitationStats['total'];
$sent = (int)$invitationStats['sent'];
$opened = (int)$invitationStats['opened'];
$bounced = (int)$invitationStats['bounced'];
$accepted = (int)$invitationStats['accepted'];

$openRate = ($sent + $opened + $accepted) > 0
    ? round(($opened + $accepted) / ($sent + $opened + $accepted) * 100, 1)
    : 0;

$bounceRate = ($sent + $bounced) > 0
    ? round($bounced / ($sent + $bounced) * 100, 1)
    : 0;

$acceptRate = ($sent + $opened + $accepted) > 0
    ? round($accepted / ($sent + $opened + $accepted) * 100, 1)
    : 0;

api_ok([
    'meeting_id' => $meetingId,
    'invitations' => [
        'total' => $total,
        'pending' => (int)$invitationStats['pending'],
        'sent' => $sent,
        'opened' => $opened,
        'accepted' => $accepted,
        'declined' => (int)$invitationStats['declined'],
        'bounced' => $bounced,
    ],
    'engagement' => [
        'total_opens' => (int)$invitationStats['total_opens'],
        'total_clicks' => (int)$invitationStats['total_clicks'],
        'open_rate' => $openRate,
        'bounce_rate' => $bounceRate,
        'accept_rate' => $acceptRate,
    ],
    'queue' => $queueByStatus,
    'events' => $eventsByType,
]);
