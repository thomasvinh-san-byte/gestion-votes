<?php
// public/api/v1/projector_state.php
// Etat compact pour l'écran projecteur (ACTIVE/CLOSED/IDLE) + gestion vote secret.
// Supporte ?meeting_id= pour cibler une séance explicite.

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
    $tenantId    = api_current_tenant_id();

    $requestedId = trim((string)($_GET['meeting_id'] ?? ''));

    // --- Meeting resolution ---
    if ($requestedId !== '') {
        // Explicit meeting_id: show it as long as it's not archived
        $meeting = $meetingRepo->findByIdForTenant($requestedId, $tenantId);
        if (!$meeting || ($meeting['status'] ?? '') === 'archived') {
            api_fail('meeting_not_found', 404);
        }
        $meetingId     = (string)$meeting['id'];
        $meetingTitle  = (string)$meeting['title'];
        $meetingStatus = (string)$meeting['status'];
    } else {
        // Auto-detect: only live meetings
        $liveMeetings = $meetingRepo->listLiveForTenant($tenantId);

        if (count($liveMeetings) === 0) {
            api_fail('no_live_meeting', 404);
        }

        if (count($liveMeetings) > 1) {
            // Multiple live meetings — frontend must choose
            api_ok([
                'choose'   => true,
                'meetings' => array_map(fn($m) => [
                    'id'         => (string)$m['id'],
                    'title'      => (string)$m['title'],
                    'started_at' => (string)($m['started_at'] ?? ''),
                ], $liveMeetings),
            ]);
        }

        // Single live meeting — auto-select
        $m = $liveMeetings[0];
        $meetingId     = (string)$m['id'];
        $meetingTitle  = (string)$m['title'];
        $meetingStatus = 'live';
    }

    // --- Motion state ---
    $open   = $motionRepo->findOpenForProjector($meetingId);
    $closed = $motionRepo->findLastClosedForProjector($meetingId);

    $phase  = 'idle';
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
        'meeting_title'  => $meetingTitle,
        'meeting_status' => $meetingStatus,
        'phase'          => $phase,
        'motion'         => $motion,
        'total_motions'  => $totalMotions,
        'eligible_count' => $eligibleCount,
    ]);

} catch (Throwable $e) {
    error_log('Error in projector_state.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
