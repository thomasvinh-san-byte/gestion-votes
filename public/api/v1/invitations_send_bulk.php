<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Service\MailerService;

api_require_role('operator');
$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$dryRun    = (bool)($input['dry_run'] ?? false);
$onlyUnsent = (bool)($input['only_unsent'] ?? true);
$limit     = (int)($input['limit'] ?? 0);

if ($meetingId === '') api_fail('missing_meeting_id', 400);

global $config;

$meetingRepo    = new MeetingRepository();
$memberRepo     = new MemberRepository();
$invitationRepo = new InvitationRepository();

// meeting title
$meetingTitle = $meetingRepo->findTitle($meetingId) ?? $meetingId;

// members with emails
$tenantId = (string)($GLOBALS['APP_TENANT_ID'] ?? api_current_tenant_id());
$members = $memberRepo->listActiveWithEmail($tenantId);

if ($limit > 0) $members = array_slice($members, 0, $limit);

$mailer = new MailerService($config ?? []);
if (!$mailer->isConfigured() && !$dryRun) {
  api_fail('smtp_not_configured', 400);
}

$sent = 0; $skipped = 0; $errors = []; $skippedNoEmail = []; $skippedAlreadySent = [];

foreach ($members as $m) {
  $memberId = (string)$m['id'];
  $memberName = (string)($m['full_name'] ?? '');
  $email = trim((string)($m['email'] ?? ''));
  if ($email === '') {
    $skipped++;
    $skippedNoEmail[] = $memberName ?: $memberId;
    continue;
  }

  if ($onlyUnsent) {
    $st = $invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId);
    if ($st === 'sent') {
      $skipped++;
      $skippedAlreadySent[] = $memberName ?: $email;
      continue;
    }
  }

  $token = bin2hex(random_bytes(32));
  $invitationRepo->upsertBulk(
    $tenantId,
    $meetingId,
    $memberId,
    $email,
    $token,
    $dryRun ? 'pending' : 'sent',
    $dryRun ? null : date('c')
  );

  $appUrl = (string)(($config['app']['url'] ?? '') ?: 'http://localhost:8080');
  $voteUrl = rtrim($appUrl, '/') . "/vote.htmx.html?token=" . rawurlencode($token);

  if ($dryRun) {
    $sent++;
    continue;
  }

  // render template
  $meetingTitleLocal = $meetingTitle;
  $memberNameLocal = $memberName;
  ob_start();
  $meetingTitle = $meetingTitleLocal;
  $memberName = $memberNameLocal;
  $voteUrlVar = $voteUrl;
  $appUrlVar = $appUrl;

  // template variables expected: $meetingTitle, $memberName, $voteUrl, $appUrl
  $voteUrl = $voteUrlVar;
  $appUrl = $appUrlVar;
  include __DIR__ . '/../../../app/Templates/email_invitation.php';
  $html = ob_get_clean();

  $subject = "Invitation de vote – " . $meetingTitleLocal;
  $res = $mailer->send($email, $subject, $html);

  if (!$res['ok']) {
    $errors[] = ['member_id'=>$memberId,'email'=>$email,'error'=>$res['error']];
    // mark as bounced (send failure)
    $invitationRepo->markBounced($meetingId, $memberId);
  } else {
    $sent++;
  }
}

api_ok([
  'meeting_id' => $meetingId,
  'meeting_title' => $meetingTitle,
  'dry_run' => $dryRun,
  'sent' => $sent,
  'skipped' => $skipped,
  'skipped_no_email' => $skippedNoEmail,
  'skipped_already_sent' => $skippedAlreadySent,
  'errors' => $errors,
]);
