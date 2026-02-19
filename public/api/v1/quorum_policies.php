<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;

api_require_role('operator');
api_request('GET');

try {
    $repo = new PolicyRepository();
    $rows = $repo->listQuorumPolicies(api_current_tenant_id());
    api_ok(['items' => $rows]);
} catch (Throwable $e) {
    error_log('Error in quorum_policies.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
