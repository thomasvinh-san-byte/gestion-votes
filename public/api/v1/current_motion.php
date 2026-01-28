<?php
// public/api/v1/current_motion.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('invalid_request', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
    }

    $motion = db_select_one(
        "
        SELECT
          id,
          agenda_id,
          title,
          description,
          secret,
          vote_policy_id,
          quorum_policy_id,
          opened_at,
          closed_at
        FROM motions
        WHERE tenant_id = :tid
          AND meeting_id = :meeting_id
          AND opened_at IS NOT NULL
          AND closed_at IS NULL
        ORDER BY opened_at DESC
        LIMIT 1
        ",
        [':tid' => DEFAULT_TENANT_ID, ':meeting_id' => $meetingId]
    );

    api_ok(['motion' => $motion]); // motion peut être null
} catch (Throwable $e) {
    error_log('Error in current_motion.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
