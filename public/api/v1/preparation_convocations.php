<?php
// public/api/v1/preparation_convocations.php
// GET: list convocations for a meeting (with member names)
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
    }

    $items = db_all(
        "SELECT c.*, m.display_name AS member_name, m.email AS member_email
         FROM preparation_convocations c
         JOIN members m ON m.id = c.member_id AND m.tenant_id = c.tenant_id
         WHERE c.tenant_id = :tid AND c.meeting_id = :mid
         ORDER BY m.display_name",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );

    // Summary
    $total = count($items);
    $sent = 0; $confirmed = 0; $declined = 0;
    foreach ($items as $item) {
        if (in_array($item['status'], ['sent', 'opened'], true)) $sent++;
        if ($item['status'] === 'confirmed') $confirmed++;
        if ($item['status'] === 'declined') $declined++;
    }

    api_ok([
        'items' => $items,
        'summary' => [
            'total' => $total,
            'sent' => $sent,
            'confirmed' => $confirmed,
            'declined' => $declined,
            'draft' => $total - $sent - $confirmed - $declined,
        ]
    ]);

} catch (Throwable $e) {
    error_log('preparation_convocations.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
