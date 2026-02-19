<?php
// Liste des politiques de vote (majorité) du tenant
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;

api_require_role('operator');
api_request('GET');

try {
    $repo = new PolicyRepository();
    $rows = $repo->listVotePolicies(api_current_tenant_id());
    api_ok(['items' => $rows]);
} catch (Throwable $e) {
    error_log('Error in vote_policies.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
