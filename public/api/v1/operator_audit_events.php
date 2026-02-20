<?php
// public/api/v1/operator_audit_events.php
// Like meeting_audit_events.php but accessible to operator/admin.
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_request('GET');

api_require_role(['operator','admin','trust']);

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

$meetingRepo = new MeetingRepository();

// Vérifier la séance
if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
}

$rows = $meetingRepo->listAuditEventsFiltered(
    api_current_tenant_id(),
    $meetingId,
    $limit,
    $resourceType,
    $action,
    $q
);

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
