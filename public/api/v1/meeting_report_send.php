<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MailerService.php';

api_require_role('operator');
$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$toEmail   = trim((string)($input['email'] ?? ''));

if ($meetingId === '' || $toEmail === '') api_fail('missing_meeting_or_email', 400);

global $pdo, $config;

$mt = $pdo->prepare("SELECT title FROM meetings WHERE id = :id");
$mt->execute([':id' => $meetingId]);
$meetingTitle = (string)($mt->fetchColumn() ?: $meetingId);

$appUrl = (string)(($config['app']['url'] ?? '') ?: 'http://localhost:8080');
$reportUrl = rtrim($appUrl, '/') . "/api/v1/meeting_report.php?meeting_id=" . rawurlencode($meetingId);

$mailer = new MailerService($config ?? []);
if (!$mailer->isConfigured()) api_fail('smtp_not_configured', 400);

// Render HTML template
ob_start();
include __DIR__ . '/../../../app/templates/email_report.php';
$html = ob_get_clean();

$subject = "PV / Résultats – " . $meetingTitle;
$res = $mailer->send($toEmail, $subject, $html);

if (!$res['ok']) api_fail('mail_send_failed', 500, ['detail'=>$res['error']]);

api_ok(['ok'=>true]);
