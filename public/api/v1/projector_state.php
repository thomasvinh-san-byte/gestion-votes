<?php
// public/api/v1/projector_state.php
// Etat compact pour l'écran projecteur (ACTIVE/CLOSED/IDLE) + gestion vote secret.

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

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

    // Motion ouverte (ACTIVE) : opened_at non null & closed_at null.
    $open = $motionRepo->findOpenForProjector($meetingId);

    // Dernière motion clôturée (CLOSED) : closed_at non null.
    $closed = $motionRepo->findLastClosedForProjector($meetingId);

    $phase = 'idle';
    $motion = null;

    if ($open) {
        $phase = 'active';
        $motion = [
            'id'     => (string)$open['id'],
            'title'  => (string)$open['title'],
            'secret' => (bool)$open['secret'],
        ];
    } elseif ($closed) {
        $phase = 'closed';
        $motion = [
            'id'     => (string)$closed['id'],
            'title'  => (string)$closed['title'],
            'secret' => (bool)$closed['secret'],
        ];
    }

    api_ok([
        'meeting_id'     => $meetingId,
        'meeting_title'  => (string)$meeting['meeting_title'],
        'meeting_status' => (string)$meeting['meeting_status'],
        'phase'          => $phase,
        'motion'         => $motion,
    ]);

} catch (PDOException $e) {
    error_log('Database error in projector_state.php: ' . $e->getMessage());
    api_fail('database_error', 500, ['detail' => $e->getMessage()]);
}
