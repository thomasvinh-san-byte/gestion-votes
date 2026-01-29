<?php
// public/api/v1/meetings_index.php
require __DIR__ . '/../../../app/api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('method_not_allowed', 405);
}

require_role('operator');

$limit = (int)($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 200) $limit = 50;

$rows = db_select_all(
    "SELECT id AS meeting_id, id, title, status::text AS status, created_at, started_at, ended_at, archived_at, validated_at
     FROM meetings
     WHERE tenant_id = ?
     ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC
     LIMIT $limit",
    [DEFAULT_TENANT_ID]
);

json_ok(['meetings' => $rows]);