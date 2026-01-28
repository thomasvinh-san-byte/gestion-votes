<?php
require __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/services/QuorumEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_err('method_not_allowed', 405);
}

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
$motionId  = trim((string)($_GET['motion_id'] ?? ''));

try {
    if ($motionId !== '') {
        $res = QuorumEngine::computeForMotion($motionId);
    } elseif ($meetingId !== '') {
        $res = QuorumEngine::computeForMeeting($meetingId);
    } else {
        json_err('missing_params', 400, ['detail' => 'meeting_id ou motion_id requis']);
    }

    json_ok($res);
} catch (Throwable $e) {
    error_log("quorum_status error: " . $e->getMessage());
    json_err('quorum_error', 500, ['detail' => $e->getMessage()]);
}
