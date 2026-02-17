<?php
declare(strict_types=1);

/**
 * proxies_delete.php - Supprimer une procuration
 *
 * POST /api/v1/proxies_delete.php
 * Body: { "meeting_id": "uuid", "proxy_id": "uuid" }
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\ProxyRepository;

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
$meetingRepo = new MeetingRepository();
$proxyRepo = new ProxyRepository();

// Verifier que la seance existe et n'est pas validee/archivee
api_guard_meeting_not_validated($meetingId);

$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

if ($meeting['status'] === 'archived') {
    api_fail('meeting_archived', 409, ['detail' => 'Séance archivée, modification impossible']);
}

// Verifier que la procuration existe
$proxy = $proxyRepo->findWithNames($proxyId, $meetingId);

if (!$proxy) {
    api_fail('proxy_not_found', 404);
}

// Supprimer la procuration
$deleted = $proxyRepo->deleteProxy($proxyId, $meetingId);

if ($deleted === 0) {
    api_fail('delete_failed', 500);
}

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
