<?php
// public/api/v1/preparation_documents_status.php
// POST: update document status (received, approved, rejected)
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_require_role('operator');
    $in = api_request('POST');

    $id = api_require_uuid($in, 'id');
    $meetingId = api_require_uuid($in, 'meeting_id');

    $status = trim((string)($in['status'] ?? ''));
    $validStatuses = ['pending', 'requested', 'received', 'approved', 'rejected'];
    if (!in_array($status, $validStatuses, true)) {
        api_fail('invalid_status', 422, ['detail' => 'status invalide. Valeurs: ' . implode(', ', $validStatuses)]);
    }

    // Verify document exists and belongs to meeting
    $doc = db_one(
        "SELECT id, status AS current_status FROM preparation_documents
         WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid",
        [':tid' => DEFAULT_TENANT_ID, ':id' => $id, ':mid' => $meetingId]
    );
    if (!$doc) api_fail('document_not_found', 404);

    $userId = api_current_user_id();

    // Update with appropriate timestamp
    $extraSql = '';
    if ($status === 'received') {
        $extraSql = ', received_at = now(), uploaded_by_user_id = NULLIF(:uid, \'\')::uuid';
    } elseif ($status === 'approved') {
        $extraSql = ', uploaded_by_user_id = NULLIF(:uid, \'\')::uuid';
    } elseif ($status === 'requested') {
        $extraSql = ', requested_at = now()';
    }

    $params = [':tid' => DEFAULT_TENANT_ID, ':id' => $id, ':status' => $status];
    if (str_contains($extraSql, ':uid')) {
        $params[':uid'] = $userId ?? '';
    }

    db_execute(
        "UPDATE preparation_documents SET status = :status" . $extraSql . " WHERE tenant_id = :tid AND id = :id",
        $params
    );

    audit_log('document_status_changed', 'preparation_document', $id, [
        'meeting_id' => $meetingId,
        'old_status' => $doc['current_status'],
        'new_status' => $status,
    ], $meetingId);

    api_ok(['id' => $id, 'status' => $status]);

} catch (Throwable $e) {
    error_log('preparation_documents_status.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
