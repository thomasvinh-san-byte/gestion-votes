<?php
// public/api/v1/meeting_audit.php
require __DIR__ . '/../../../app/api.php';

api_require_role(['auditor', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

// Vérifier la séance
$exists = db_scalar("SELECT 1 FROM meetings WHERE id = ? AND tenant_id = ?", [$meetingId, api_current_tenant_id()]);
if (!$exists) {
    api_fail('meeting_not_found', 404);
}

global $pdo;

// Audit pour la séance elle-même + ses motions
$stmt = $pdo->prepare("
    SELECT
      id,
      action,
      resource_type,
      resource_id,
      payload,
      created_at
    FROM audit_events
    WHERE tenant_id = ?
      AND (
        (resource_type = 'meeting' AND resource_id = ?)
        OR
        (resource_type = 'motion' AND resource_id IN (
            SELECT id FROM motions WHERE meeting_id = ?
        ))
      )
    ORDER BY created_at ASC
");
$stmt->execute([api_current_tenant_id(), $meetingId, $meetingId]);
$rows = $stmt->fetchAll();

api_ok([
    'meeting_id' => $meetingId,
    'events'     => $rows,
]);
