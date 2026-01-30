<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;

api_request('GET');

$repo = new PolicyRepository();
$rows = $repo->listQuorumPolicies(api_current_tenant_id());

api_ok(['items' => $rows]);
