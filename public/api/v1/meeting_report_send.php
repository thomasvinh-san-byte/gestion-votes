<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Service\MailerService;

api_require_role('operator');
$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
$toEmail   = trim((string)($input['email'] ?? ''));

if ($meetingId === '' || $toEmail === '') api_fail('missing_meeting_or_email', 400);
if (!api_is_uuid($meetingId)) api_fail('invalid_meeting_id', 400);

api_guard_meeting_not_validated($meetingId);

try {
    global $config;

    $tenantId = api_current_tenant_id();
    $repo = new MeetingRepository();
    $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
    $meetingTitle = (string)(($meeting['title'] ?? '') ?: $meetingId);

    $appUrl = (string)(($config['app']['url'] ?? '') ?: 'http://localhost:8080');
    $reportUrl = rtrim($appUrl, '/') . "/api/v1/meeting_report.php?meeting_id=" . rawurlencode($meetingId);

    $mailer = new MailerService($config ?? []);
    if (!$mailer->isConfigured()) api_fail('smtp_not_configured', 400);

    // Render HTML template
    ob_start();
    include __DIR__ . '/../../../app/Templates/email_report.php';
    $html = ob_get_clean();

    $subject = "PV / Résultats – " . $meetingTitle;
    $res = $mailer->send($toEmail, $subject, $html);

    if (!$res['ok']) {
        error_log('Mail send failed: ' . ($res['error'] ?? 'unknown'));
        api_fail('mail_send_failed', 500);
    }

    api_ok(['ok'=>true]);
} catch (Throwable $e) {
    error_log('Error in meeting_report_send.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
