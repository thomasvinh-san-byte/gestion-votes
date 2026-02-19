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

try {
    $html = MeetingReportService::renderHtml($meetingId, $showVoters);

    // Print-friendly headers
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $html;
} catch (Throwable $e) {
    error_log('Error in export_pv_html.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body><h1>Erreur</h1><p>Impossible de générer le PV.</p></body></html>';
}
