<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\MeetingReportService;

// Export PV HTML for operator/trust/admin (MVP: auth currently disabled)
api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "missing_meeting_id";
    exit;
}

$showVoters = ((string)($_GET['show_voters'] ?? '') === '1');

$html = MeetingReportService::renderHtml($meetingId, $showVoters);

// Print-friendly headers
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $html;
