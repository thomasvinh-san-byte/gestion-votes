<?php
// public/api/v1/meetings.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('operator');

$data = api_request('GET', 'POST');
$method = api_method();
$repo = new MeetingRepository();

try {
    if ($method === 'GET') {
        // Single meeting by id
        $singleId = trim($data['id'] ?? '');
        if ($singleId !== '') {
            $meeting = $repo->findByIdForTenant($singleId, api_current_tenant_id());
            if (!$meeting) {
                api_fail('meeting_not_found', 404);
            }
            api_ok($meeting);
        }
        // List meetings - filter by active_only if requested
        $activeOnly = filter_var($data['active_only'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        if ($activeOnly) {
            $rows = $repo->listActiveByTenant(api_current_tenant_id());
        } else {
            $rows = $repo->listByTenant(api_current_tenant_id());
        }
        api_ok(['meetings' => $rows]);

    } elseif ($method === 'POST') {
        $v = ValidationSchemas::meeting()->validate($data);
        $v->failIfInvalid();

        $title       = $v->get('title');
        $description = $v->get('description');
        $scheduledAt = $v->get('scheduled_at');
        $location    = $v->get('location');
        $meetingType = $v->get('meeting_type', 'ag_ordinaire');

        $id = $repo->generateUuid();
        $repo->create(
            $id,
            api_current_tenant_id(),
            $title,
            $description ?: null,
            $scheduledAt ?: null,
            $location ?: null,
            $meetingType
        );

        // Auto-assign tenant's first vote/quorum policies as defaults
        $policyRepo = new PolicyRepository();
        $votePolicies = $policyRepo->listVotePolicies(api_current_tenant_id());
        $quorumPolicies = $policyRepo->listQuorumPolicies(api_current_tenant_id());
        $defaults = [];
        if (!empty($votePolicies))   $defaults['vote_policy_id']   = $votePolicies[0]['id'];
        if (!empty($quorumPolicies)) $defaults['quorum_policy_id'] = $quorumPolicies[0]['id'];
        if ($defaults) {
            $repo->updateFields($id, api_current_tenant_id(), $defaults);
        }

        audit_log('meeting_created', 'meeting', $id, [
            'title'       => $title,
            'scheduled_at'=> $scheduledAt,
            'location'    => $location,
        ]);

        api_ok([
            'meeting_id' => $id,
            'title'      => $title,
        ], 201);

    }

} catch (Throwable $e) {
    error_log('Error in meetings.php: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
