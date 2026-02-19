<?php
// public/api/v1/projector_state.php
// Etat compact pour l'écran projecteur (ACTIVE/CLOSED/IDLE) + gestion vote secret.

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

api_require_role('public');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

try {
    $meetingRepo = new MeetingRepository();
    $motionRepo  = new MotionRepository();

    // Séance "courante" pour le tenant : privilégie live, puis closed, puis draft.
    $meeting = $meetingRepo->findCurrentForTenant(api_current_tenant_id());

    if (!$meeting) {
        api_fail('no_live_meeting', 404);
    }

    $meetingId = (string)$meeting['meeting_id'];
    $tenantId  = api_current_tenant_id();

    // Motion ouverte (ACTIVE) : opened_at non null & closed_at null.
    $open = $motionRepo->findOpenForProjector($meetingId);

    // Dernière motion clôturée (CLOSED) : closed_at non null.
    $closed = $motionRepo->findLastClosedForProjector($meetingId);

    $phase = 'idle';
    $motion = null;

    if ($open) {
        $phase = 'active';
        $motion = [
            'id'          => (string)$open['id'],
            'title'       => (string)$open['title'],
            'description' => (string)($open['description'] ?? ''),
            'body'        => (string)($open['body'] ?? ''),
            'secret'      => (bool)$open['secret'],
            'position'    => $open['position'] !== null ? (int)$open['position'] : null,
        ];
    } elseif ($closed) {
        $phase = 'closed';
        $motion = [
            'id'          => (string)$closed['id'],
            'title'       => (string)$closed['title'],
            'description' => (string)($closed['description'] ?? ''),
            'body'        => (string)($closed['body'] ?? ''),
            'secret'      => (bool)$closed['secret'],
            'position'    => $closed['position'] !== null ? (int)$closed['position'] : null,
        ];
    }

    // KPI: total motions + eligible voter count
    $totalMotions  = $motionRepo->countForMeeting($meetingId);
    $eligibleCount = $meetingRepo->countActiveMembers($tenantId);

    api_ok([
        'meeting_id'     => $meetingId,
        'meeting_title'  => (string)$meeting['meeting_title'],
        'meeting_status' => (string)$meeting['meeting_status'],
        'phase'          => $phase,
        'motion'         => $motion,
        'total_motions'  => $totalMotions,
        'eligible_count' => $eligibleCount,
    ]);

} catch (Throwable $e) {
    error_log('Error in projector_state.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
