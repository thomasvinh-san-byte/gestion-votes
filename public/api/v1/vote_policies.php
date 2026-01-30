<?php
// Liste des politiques de vote (majorité) du tenant
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;

api_request('GET');

$repo = new PolicyRepository();
$rows = $repo->listVotePolicies(api_current_tenant_id());

api_ok(['items' => $rows]);
