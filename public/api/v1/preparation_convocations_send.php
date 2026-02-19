<?php
// public/api/v1/preparation_convocations_send.php
// POST: mark all draft convocations as "sent" for a meeting
// In a real system this would trigger email sending; here we just update status.
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_require_role('operator');
    $in = api_request('POST');

    $meetingId = api_require_uuid($in, 'meeting_id');

    // Verify meeting exists
    $meeting = db_one(
        "SELECT id FROM meetings WHERE tenant_id = :tid AND id = :mid",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );
    if (!$meeting) api_fail('meeting_not_found', 404);

    // Mark all draft convocations as sent
    $updated = db_execute(
        "UPDATE preparation_convocations
         SET status = 'sent', sent_at = now()
         WHERE tenant_id = :tid AND meeting_id = :mid AND status = 'draft'",
        [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );

    audit_log('convocations_sent', 'preparation_convocation', null, [
        'meeting_id' => $meetingId,
        'sent_count' => $updated,
    ], $meetingId);

    api_ok(['sent' => $updated]);

} catch (Throwable $e) {
    error_log('preparation_convocations_send.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
