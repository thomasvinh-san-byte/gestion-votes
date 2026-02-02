<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Service\QuorumEngine;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
$motionId  = trim((string)($_GET['motion_id'] ?? ''));

try {
    if ($motionId !== '') {
        $res = QuorumEngine::computeForMotion($motionId);
    } elseif ($meetingId !== '') {
        $res = QuorumEngine::computeForMeeting($meetingId);
    } else {
        api_fail('missing_params', 400, ['detail' => 'meeting_id ou motion_id requis']);
    }

    // Add flat convenience fields for frontend consumption
    $primary = $res['details']['primary'] ?? [];
    $res['ratio'] = $primary['ratio'] ?? 0;
    $res['threshold'] = $primary['threshold'] ?? 0.5;
    $res['present'] = $res['numerator']['members'] ?? 0;
    $res['total_eligible'] = $res['eligible']['members'] ?? 0;
    $res['required'] = (int)ceil(($primary['threshold'] ?? 0.5) * max(1, $res['eligible']['members'] ?? 0));
    $res['mode'] = $primary['basis'] ?? 'simple';

    api_ok($res);
} catch (Throwable $e) {
    error_log("quorum_status error: " . $e->getMessage());
    api_fail('quorum_error', 500, ['detail' => $e->getMessage()]);
}
