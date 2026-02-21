<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Service\EmailQueueService;
use AgVote\Service\EmailTemplateService;
use AgVote\Service\MailerService;
use DateTime;
use Throwable;

final class EmailController extends AbstractController {
    public function preview(): void {
        $input = api_request('POST');

        $bodyHtml = trim((string) ($input['body_html'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $customVariables = isset($input['custom_variables']) && is_array($input['custom_variables'])
            ? $input['custom_variables']
            : null;

        if ($bodyHtml === '') {
            api_fail('missing_body_html', 400);
        }

        global $config;
        $service = new EmailTemplateService($config ?? []);

        $validation = $service->validate($bodyHtml);
        $previewHtml = $service->preview($bodyHtml, $customVariables);
        $previewSubject = $subject !== '' ? $service->preview($subject, $customVariables) : '';

        api_ok([
            'preview_html' => $previewHtml,
            'preview_subject' => $previewSubject,
            'validation' => $validation,
            'available_variables' => $service->listAvailableVariables(),
        ]);
    }

    public function schedule(): void {
        try {
            $input = api_request('POST');

            $meetingId = trim((string) ($input['meeting_id'] ?? ''));
            $templateId = isset($input['template_id']) ? trim((string) $input['template_id']) : null;
            $scheduledAt = isset($input['scheduled_at']) ? trim((string) $input['scheduled_at']) : null;
            $onlyUnsent = !isset($input['only_unsent']) || (bool) $input['only_unsent'];

            if ($meetingId === '' || !api_is_uuid($meetingId)) {
                api_fail('missing_meeting_id', 400);
            }

            api_guard_meeting_not_validated($meetingId);

            if ($templateId !== null && $templateId !== '' && !api_is_uuid($templateId)) {
                api_fail('invalid_template_id', 400);
            }

            if ($scheduledAt !== null && $scheduledAt !== '') {
                $dt = DateTime::createFromFormat(DateTime::ATOM, $scheduledAt);
                if (!$dt) {
                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
                }
                if (!$dt) {
                    $dt = DateTime::createFromFormat('Y-m-d H:i', $scheduledAt);
                }
                if (!$dt) {
                    api_fail('invalid_scheduled_at', 400, ['detail' => 'Format attendu: ISO 8601 ou Y-m-d H:i:s']);
                }
                $scheduledAt = $dt->format('c');
            }

            global $config;
            $service = new EmailQueueService($config ?? []);
            $tenantId = api_current_tenant_id();

            $result = $service->scheduleInvitations($tenantId, $meetingId, $templateId, $scheduledAt, $onlyUnsent);

            audit_log('email.schedule', 'meeting', $meetingId, [
                'scheduled' => $result['scheduled'],
                'skipped' => $result['skipped'],
            ], $meetingId);

            api_ok([
                'meeting_id' => $meetingId,
                'scheduled' => $result['scheduled'],
                'skipped' => $result['skipped'],
                'scheduled_at' => $scheduledAt ?? 'immediate',
                'errors' => $result['errors'],
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \AgVote\Core\Http\ApiResponseException) {
                throw $e;
            }
            error_log('Error in EmailController::schedule: ' . $e->getMessage());
            api_fail('server_error', 500, ['detail' => $e->getMessage()]);
        }
    }

    public function sendBulk(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        $dryRun = (bool) ($input['dry_run'] ?? false);
        $onlyUnsent = (bool) ($input['only_unsent'] ?? true);
        $limit = (int) ($input['limit'] ?? 0);

        global $config;

        $meetingRepo = new MeetingRepository();
        $memberRepo = new MemberRepository();
        $invitationRepo = new InvitationRepository();

        $meetingTitle = $meetingRepo->findTitle($meetingId) ?? $meetingId;

        $tenantId = api_current_tenant_id();
        $members = $memberRepo->listActiveWithEmail($tenantId);

        if ($limit > 0) {
            $members = array_slice($members, 0, $limit);
        }

        $mailer = new MailerService($config ?? []);
        if (!$mailer->isConfigured() && !$dryRun) {
            api_fail('smtp_not_configured', 400);
        }

        $sent = 0;
        $skipped = 0;
        $errors = [];
        $skippedNoEmail = [];
        $skippedAlreadySent = [];

        foreach ($members as $m) {
            $memberId = (string) $m['id'];
            $memberName = (string) ($m['full_name'] ?? '');
            $email = trim((string) ($m['email'] ?? ''));
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
                $dryRun ? null : date('c'),
            );

            $appUrl = (string) (($config['app']['url'] ?? '') ?: 'http://localhost:8080');
            $voteUrl = rtrim($appUrl, '/') . '/vote.htmx.html?token=' . rawurlencode($token);

            if ($dryRun) {
                $sent++;
                continue;
            }

            $meetingTitleLocal = $meetingTitle;
            $memberNameLocal = $memberName;
            ob_start();
            $meetingTitle = $meetingTitleLocal;
            $memberName = $memberNameLocal;
            $voteUrlVar = $voteUrl;
            $appUrlVar = $appUrl;
            $voteUrl = $voteUrlVar;
            $appUrl = $appUrlVar;
            include __DIR__ . '/../Templates/email_invitation.php';
            $html = ob_get_clean();

            $subject = 'Invitation de vote – ' . $meetingTitleLocal;
            $res = $mailer->send($email, $subject, $html);

            if (!$res['ok']) {
                $errors[] = ['member_id' => $memberId, 'email' => $email, 'error' => $res['error']];
                $invitationRepo->markBounced($meetingId, $memberId, $tenantId);
            } else {
                $sent++;
            }
        }

        if (!$dryRun) {
            audit_log('email.send_bulk', 'meeting', $meetingId, [
                'sent' => $sent,
                'skipped' => $skipped,
                'errors_count' => count($errors),
            ], $meetingId);
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
    }
}
