<?php
// public/api/v1/meetings_archive.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role('operator');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');

try {
    $repo = new MeetingRepository();
    $rows = $repo->listArchived(api_current_tenant_id(), $from, $to);
    api_ok(['meetings' => $rows]);
} catch (PDOException $e) {
    error_log('meetings_archive.php: ' . $e->getMessage());
    api_fail('database_error', 500);
}
