<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MailerService.php';

api_require_role('operator');
$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$dryRun    = (bool)($input['dry_run'] ?? false);
$onlyUnsent = (bool)($input['only_unsent'] ?? true);
$limit     = (int)($input['limit'] ?? 0);

if ($meetingId === '') api_fail('missing_meeting_id', 400);

global $pdo, $config;

// meeting title
$mt = $pdo->prepare("SELECT title FROM meetings WHERE id = :id");
$mt->execute([':id' => $meetingId]);
$meetingTitle = (string)($mt->fetchColumn() ?: $meetingId);

// members with emails
$sql = "SELECT id, full_name, email
        FROM members
        WHERE tenant_id = :tenant_id
          AND is_active = true
          AND email IS NOT NULL
          AND email <> ''
        ORDER BY full_name ASC";
$tenantId = (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
$stmt = $pdo->prepare($sql);
$stmt->execute([':tenant_id' => $tenantId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($limit > 0) $members = array_slice($members, 0, $limit);

$mailer = new MailerService($config ?? []);
if (!$mailer->isConfigured() && !$dryRun) {
  api_fail('smtp_not_configured', 400);
}

$sent = 0; $skipped = 0; $errors = [];

foreach ($members as $m) {
  $memberId = (string)$m['id'];
  $memberName = (string)($m['full_name'] ?? '');
  $email = trim((string)($m['email'] ?? ''));
  if ($email === '') { $skipped++; continue; }

  if ($onlyUnsent) {
    $chk = $pdo->prepare("SELECT status FROM invitations WHERE meeting_id=:mid AND member_id=:mem LIMIT 1");
    $chk->execute([':mid'=>$meetingId, ':mem'=>$memberId]);
    $st = $chk->fetchColumn();
    if ($st === 'sent') { $skipped++; continue; }
  }

  $token = bin2hex(random_bytes(16));
  $pdo->prepare(
    "INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token, status, sent_at, updated_at)
     VALUES (:tenant_id, :meeting_id, :member_id, :email, :token, :status, :sent_at, now())
     ON CONFLICT (tenant_id, meeting_id, member_id)
     DO UPDATE SET token=EXCLUDED.token,
                   email=COALESCE(EXCLUDED.email, invitations.email),
                   status=EXCLUDED.status,
                   sent_at=EXCLUDED.sent_at,
                   updated_at=now()"
  )->execute([
      ':tenant_id'  => $tenantId,
      ':meeting_id' => $meetingId,
      ':member_id'  => $memberId,
      ':email'      => $email,
      ':token'      => $token,
      ':status'     => $dryRun ? 'pending' : 'sent',
      ':sent_at'    => $dryRun ? null : date('c'),
  ]);

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
  include __DIR__ . '/../../../app/templates/email_invitation.php';
  $html = ob_get_clean();

  $subject = "Invitation de vote – " . $meetingTitleLocal;
  $res = $mailer->send($email, $subject, $html);

  if (!$res['ok']) {
    $errors[] = ['member_id'=>$memberId,'email'=>$email,'error'=>$res['error']];
    // mark as bounced (send failure)
    $pdo->prepare("UPDATE invitations SET status='bounced', updated_at=now() WHERE meeting_id=:mid AND member_id=:mem")
        ->execute([':mid'=>$meetingId, ':mem'=>$memberId]);
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
  'errors' => $errors,
]);
