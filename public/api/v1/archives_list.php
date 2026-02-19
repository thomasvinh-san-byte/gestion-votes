<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

// Archives require at least viewer role (authenticated users only)
api_require_role('viewer');
api_request('GET');

$repo = new MeetingRepository();
$rows = $repo->listArchivedWithReports(api_current_tenant_id());

api_ok(['items' => $rows]);
