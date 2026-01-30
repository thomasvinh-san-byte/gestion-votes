<?php
// public/api/v1/projector_state.php
// Etat compact pour l'écran projecteur (ACTIVE/CLOSED/IDLE) + gestion vote secret.

require __DIR__ . '/../../../app/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

try {
    // Séance "courante" pour le tenant : privilégie live, puis closed, puis draft.
    $meeting = db_select_one(
        "
        SELECT
            m.id     AS meeting_id,
            m.title  AS meeting_title,
            m.status AS meeting_status
        FROM meetings m
        WHERE m.tenant_id = :tenant_id
          AND m.status <> 'archived'
        ORDER BY
            CASE m.status
                WHEN 'live'   THEN 1
                WHEN 'closed' THEN 2
                WHEN 'draft'  THEN 3
                ELSE 4
            END,
            m.created_at DESC
        LIMIT 1
        ",
        ['tenant_id' => api_current_tenant_id()]
    );

    if (!$meeting) {
        api_fail('no_live_meeting', 404);
    }

    $meetingId = (string)$meeting['meeting_id'];

    // Motion ouverte (ACTIVE) : opened_at non null & closed_at null.
    $open = db_select_one(
        "
        SELECT id, title, secret, opened_at
        FROM motions
        WHERE meeting_id = :meeting_id
          AND opened_at IS NOT NULL
          AND closed_at IS NULL
        ORDER BY opened_at DESC
        LIMIT 1
        ",
        ['meeting_id' => $meetingId]
    );

    // Dernière motion clôturée (CLOSED) : closed_at non null.
    $closed = db_select_one(
        "
        SELECT id, title, secret, closed_at
        FROM motions
        WHERE meeting_id = :meeting_id
          AND closed_at IS NOT NULL
        ORDER BY closed_at DESC
        LIMIT 1
        ",
        ['meeting_id' => $meetingId]
    );

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
