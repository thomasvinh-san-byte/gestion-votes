<?php
// public/api/v1/preparation_checklist_toggle.php
// POST: toggle a checklist item's checked state
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    api_require_role('operator');
    $in = api_request('POST');

    $id = api_require_uuid($in, 'id');
    $meetingId = api_require_uuid($in, 'meeting_id');
    $isChecked = (bool)($in['is_checked'] ?? false);

    // Verify item exists and belongs to meeting
    $item = db_one(
        "SELECT id FROM preparation_checklist_items
         WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid",
        [':tid' => DEFAULT_TENANT_ID, ':id' => $id, ':mid' => $meetingId]
    );
    if (!$item) api_fail('item_not_found', 404);

    $userId = api_current_user_id();

    if ($isChecked) {
        db_execute(
            "UPDATE preparation_checklist_items
             SET is_checked = true, checked_at = now(), checked_by_user_id = NULLIF(:uid, '')::uuid
             WHERE tenant_id = :tid AND id = :id",
            [':uid' => $userId ?? '', ':tid' => DEFAULT_TENANT_ID, ':id' => $id]
        );
    } else {
        db_execute(
            "UPDATE preparation_checklist_items
             SET is_checked = false, checked_at = NULL, checked_by_user_id = NULL
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => DEFAULT_TENANT_ID, ':id' => $id]
        );
    }

    audit_log('checklist_item_toggled', 'preparation_checklist', $id, [
        'meeting_id' => $meetingId,
        'is_checked' => $isChecked,
    ], $meetingId);

    api_ok(['id' => $id, 'is_checked' => $isChecked]);

} catch (Throwable $e) {
    error_log('preparation_checklist_toggle.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
