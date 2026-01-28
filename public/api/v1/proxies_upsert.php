<?php
// public/api/v1/proxies_upsert.php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

$in = api_request('POST');

$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$giverId   = api_require_uuid($in, 'giver_member_id');

$receiverRaw = trim((string)($in['receiver_member_id'] ?? ''));
$scope       = trim((string)($in['scope'] ?? 'full'));

require_once __DIR__ . '/../../../app/services/ProxiesService.php';

try {
    // MVP: receiver_member_id vide => révoque
    if ($receiverRaw === '') {
        ProxiesService::revoke($meetingId, $giverId);
        api_ok([
            'ok'         => true,
            'meeting_id' => $meetingId,
            'giver_member_id' => $giverId,
            'revoked'    => true,
        ]);
    }

    if (!api_is_uuid($receiverRaw)) {
        api_fail('invalid_receiver_member_id', 400, ['detail' => 'receiver_member_id doit être un UUID ou vide (pour révoquer).']);
    }

    $row = ProxiesService::upsert($meetingId, $giverId, $receiverRaw, $scope ?: 'full');

    if (function_exists('audit_log')) {
        audit_log('proxy_upsert', 'meeting', $meetingId, [
            'giver_member_id'    => $giverId,
            'receiver_member_id' => $receiverRaw,
            'scope'              => $scope ?: 'full',
        ]);
    }

    api_ok([
        'ok'         => true,
        'meeting_id' => $meetingId,
        'proxy'      => $row,
    ]);
} catch (Throwable $e) {
    api_fail('proxy_upsert_failed', 400, ['detail' => $e->getMessage()]);
}