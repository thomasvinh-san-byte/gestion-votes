<?php
declare(strict_types=1);

/**
 * proxies_delete.php - Supprimer une procuration
 * 
 * POST /api/v1/proxies_delete.php
 * Body: { "meeting_id": "uuid", "proxy_id": "uuid" }
 */

require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'admin']);

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
$proxyId = trim((string)($input['proxy_id'] ?? ''));

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

if ($proxyId === '' || !api_is_uuid($proxyId)) {
    api_fail('missing_proxy_id', 400);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe et n'est pas archivée
$meeting = db_one("
    SELECT id, status FROM meetings 
    WHERE tenant_id = ? AND id = ?
", [$tenantId, $meetingId]);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

if ($meeting['status'] === 'archived') {
    api_fail('meeting_archived', 409, ['detail' => 'Séance archivée, modification impossible']);
}

// Vérifier que la procuration existe
$proxy = db_one("
    SELECT p.id, g.full_name AS giver_name, r.full_name AS receiver_name
    FROM proxies p
    JOIN members g ON g.id = p.giver_id
    JOIN members r ON r.id = p.receiver_id
    WHERE p.id = ? AND p.meeting_id = ?
", [$proxyId, $meetingId]);

if (!$proxy) {
    api_fail('proxy_not_found', 404);
}

// Supprimer la procuration
$deleted = db_exec("
    DELETE FROM proxies 
    WHERE id = ? AND meeting_id = ?
", [$proxyId, $meetingId]);

if ($deleted === 0) {
    api_fail('delete_failed', 500);
}

// Audit log
audit_log('proxy_deleted', 'proxy', $proxyId, [
    'giver_name' => $proxy['giver_name'],
    'receiver_name' => $proxy['receiver_name'],
], $meetingId);

api_ok([
    'deleted' => true,
    'proxy_id' => $proxyId,
    'giver_name' => $proxy['giver_name'],
    'receiver_name' => $proxy['receiver_name'],
]);
