<?php
// public/api/v1/meeting_audit_events.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_request('GET');

api_require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

$repo = new MeetingRepository();

if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
}

$rows = $repo->listAuditEvents($meetingId, api_current_tenant_id());

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
