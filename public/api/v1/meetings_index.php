<?php
// public/api/v1/meetings_index.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_fail('method_not_allowed', 405);
}

api_require_role('operator');

$limit = (int)($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 200) $limit = 50;

$repo = new MeetingRepository();
$rows = $repo->listByTenantCompact(api_current_tenant_id(), $limit);

api_ok(['meetings' => $rows]);
