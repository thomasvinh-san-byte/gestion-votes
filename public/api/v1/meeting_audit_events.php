<?php
// public/api/v1/meeting_audit_events.php
require __DIR__ . '/../../../app/api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('method_not_allowed', 405);
}

require_role('trust');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    json_err('missing_meeting_id', 422);
}

// Vérifier la séance
$exists = db_scalar("SELECT 1 FROM meetings WHERE id = ? AND tenant_id = ?", [$meetingId, DEFAULT_TENANT_ID]);
if (!$exists) {
    json_err('meeting_not_found', 404);
}

global $pdo;

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
    ORDER BY created_at DESC
    LIMIT 200
");
$stmt->execute([DEFAULT_TENANT_ID, $meetingId, $meetingId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
foreach ($rows as $r) {
    $payload = [];
    try {
        $payload = is_string($r['payload']) ? (json_decode($r['payload'], true) ?: []) : (array)$r['payload'];
    } catch (Throwable $e) {
        $payload = [];
    }

    $message = '';
    if (is_array($payload)) {
        $message = (string)($payload['message'] ?? '');
        if ($message === '' && isset($payload['detail'])) $message = (string)$payload['detail'];
    }

    $events[] = [
        'id' => (string)$r['id'],
        'action' => (string)($r['action'] ?? ''),
        'resource_type' => (string)($r['resource_type'] ?? ''),
        'resource_id' => (string)($r['resource_id'] ?? ''),
        'message' => $message,
        'created_at' => (string)($r['created_at'] ?? ''),
    ];
}

json_ok(['events' => $events]);