<?php
// public/api/v1/motion_delete.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

try {
    $in = api_request('POST');
    $motionId = api_require_uuid($in, 'motion_id');

    $motion = db_select_one(
        "SELECT id, meeting_id, agenda_id, opened_at, closed_at
         FROM motions
         WHERE tenant_id=:tid AND id=:id",
        [':tid'=>api_current_tenant_id(), ':id'=>$motionId]
    );
    if (!$motion) api_fail('motion_not_found', 404);

    if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
        api_fail('motion_open_locked', 409, ['detail' => 'Motion ouverte : suppression interdite.']);
    }
    if (!empty($motion['closed_at'])) {
        api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : suppression interdite.']);
    }

    db_execute(
        "DELETE FROM motions WHERE tenant_id=:tid AND id=:id",
        [':tid'=>api_current_tenant_id(), ':id'=>$motionId]
    );

    audit_log('motion_deleted', 'motion', (string)$motionId, [
        'meeting_id' => (string)$motion['meeting_id'],
        'agenda_id'  => (string)$motion['agenda_id'],
    ]);

    api_ok(['motion_id' => (string)$motionId]);
} catch (Throwable $e) {
    error_log('motion_delete.php error: '.$e->getMessage());
    api_fail('internal_error', 500, ['detail'=>'Erreur interne du serveur']);
}
