<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Service\ProxiesService;

/**
 * Consolidates 3 proxy endpoints.
 *
 * Shared pattern: meeting_id + meeting not validated.
 */
final class ProxiesController extends AbstractController
{
    public function listForMeeting(): void
    {
        api_require_role(['operator', 'trust', 'admin']);
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');

        $rows = ProxiesService::listForMeeting($meetingId);
        api_ok([
            'meeting_id' => $meetingId,
            'count' => count($rows),
            'proxies' => $rows,
        ]);
    }

    public function upsert(): void
    {
        api_require_role('operator');
        $in = api_request('POST');

        $meetingId = api_require_uuid($in, 'meeting_id');
        api_guard_meeting_not_validated($meetingId);
        $giverId = api_require_uuid($in, 'giver_member_id');

        $receiverRaw = trim((string)($in['receiver_member_id'] ?? ''));
        $scope = trim((string)($in['scope'] ?? 'full'));

        if ($receiverRaw === '') {
            ProxiesService::revoke($meetingId, $giverId);
            audit_log('proxy_revoked', 'meeting', $meetingId, [
                'giver_member_id' => $giverId,
            ]);
            api_ok([
                'ok' => true,
                'meeting_id' => $meetingId,
                'giver_member_id' => $giverId,
                'revoked' => true,
            ]);
        }

        if (!api_is_uuid($receiverRaw)) {
            api_fail('invalid_receiver_member_id', 400, [
                'detail' => 'receiver_member_id doit être un UUID ou vide (pour révoquer).',
            ]);
        }

        ProxiesService::upsert($meetingId, $giverId, $receiverRaw);

        audit_log('proxy_upsert', 'meeting', $meetingId, [
            'giver_member_id' => $giverId,
            'receiver_member_id' => $receiverRaw,
            'scope' => $scope ?: 'full',
        ]);

        api_ok([
            'ok' => true,
            'meeting_id' => $meetingId,
            'giver_member_id' => $giverId,
            'receiver_member_id' => $receiverRaw,
            'scope' => $scope ?: 'full',
        ]);
    }

    public function delete(): void
    {
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
        api_guard_meeting_not_validated($meetingId);

        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if ($meeting['status'] === 'archived') {
            api_fail('meeting_archived', 409, ['detail' => 'Séance archivée, modification impossible']);
        }

        $proxyRepo = new ProxyRepository();
        $proxy = $proxyRepo->findWithNames($proxyId, $meetingId);
        if (!$proxy) {
            api_fail('proxy_not_found', 404);
        }

        $deleted = $proxyRepo->deleteProxy($proxyId, $meetingId, $tenantId);
        if ($deleted === 0) {
            api_fail('delete_failed', 500, ['detail' => 'La suppression a échoué.']);
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
    }
}
