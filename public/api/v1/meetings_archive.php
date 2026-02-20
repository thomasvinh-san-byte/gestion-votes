<?php
// public/api/v1/meetings_archive.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role('operator');

api_request('GET');

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to']   ?? '');

try {
    $repo = new MeetingRepository();
    $rows = $repo->listArchived(api_current_tenant_id(), $from, $to);
    api_ok(['meetings' => $rows]);
} catch (Throwable $e) {
    error_log('Error in meetings_archive.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
