<?php
// public/api/v1/current_motion.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;

try {
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('invalid_request', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
    }

    $repo = new MotionRepository();
    $motion = $repo->findCurrentOpen($meetingId, api_current_tenant_id());

    api_ok(['motion' => $motion]); // motion peut être null
} catch (Throwable $e) {
    error_log('Error in current_motion.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
