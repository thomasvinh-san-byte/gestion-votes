<?php
// public/api/v1/operator_audit_events.php
// Like meeting_audit_events.php but accessible to operator/admin.
require __DIR__ . '/../../../app/api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_fail('method_not_allowed', 405);
}

require_any_role(['operator','admin','trust']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$resourceType = trim((string)($_GET['resource_type'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

// Vérifier la séance
$exists = db_scalar("SELECT 1 FROM meetings WHERE id = ? AND tenant_id = ?", [$meetingId, api_current_tenant_id()]);
if (!$exists) {
    api_fail('meeting_not_found', 404);
}

global $pdo;

$where = "WHERE tenant_id = ? AND (\n        (resource_type = 'meeting' AND resource_id = ?)\n        OR\n        (resource_type = 'motion' AND resource_id IN (SELECT id FROM motions WHERE meeting_id = ?))\n    )";
$params = [api_current_tenant_id(), $meetingId, $meetingId];

if ($resourceType !== '') {
    $where .= " AND resource_type = ?";
    $params[] = $resourceType;
}

if ($action !== '') {
    $where .= " AND action ILIKE ?";
    $params[] = "%{$action}%";
}

if ($q !== '') {
    // Recherche large dans action, resource_id, payload JSON.
    $where .= " AND (action ILIKE ? OR resource_id ILIKE ? OR payload::text ILIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

$sql = "
    SELECT
      id,
      action,
      resource_type,
      resource_id,
      payload,
      created_at
    FROM audit_events
    {$where}
    ORDER BY created_at DESC
    LIMIT {$limit}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

api_ok(['events' => $events]);
