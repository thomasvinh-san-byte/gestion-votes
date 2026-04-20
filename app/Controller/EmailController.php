<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\IdempotencyGuard;
use AgVote\Service\EmailQueueService;
use AgVote\Service\EmailTemplateService;
use AgVote\Service\MailerService;
use DateTime;

final class EmailController extends AbstractController {
    /**
     * Optional factory used to build an EmailQueueService instance.
     *
     * Defaults to null (production: build via MailerService::buildMailerConfig +
     * new EmailQueueService). Inject a callable in tests to return a stub:
     *   new EmailController(fn($config) => $mockEmailQueueService)
     *
     * @var (callable(array): EmailQueueService)|null
     */
    private $emailQueueFactory;

    public function __construct(?callable $emailQueueFactory = null)
    {
        parent::__construct();
        $this->emailQueueFactory = $emailQueueFactory;
    }

    public function preview(): void {
        $input = api_request('POST');

        $action = trim((string) ($input['action'] ?? ''));
        if ($action === 'test_smtp') {
            $this->testSmtp();
            return;
        }

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
        $input = api_request('POST');

        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }

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
        $tenantId = api_current_tenant_id();
        $mergedConfig = MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId);
        $service = new EmailQueueService($mergedConfig);

        $result = $service->scheduleInvitations($tenantId, $meetingId, $templateId, $scheduledAt, $onlyUnsent);

        audit_log('email.schedule', 'meeting', $meetingId, [
            'scheduled' => $result['scheduled'],
            'skipped' => $result['skipped'],
        ], $meetingId);

        $response = [
            'meeting_id' => $meetingId,
            'scheduled' => $result['scheduled'],
            'skipped' => $result['skipped'],
            'scheduled_at' => $scheduledAt ?? 'immediate',
            'errors' => $result['errors'],
        ];
        IdempotencyGuard::store($response);
        api_ok($response);
    }

    public function sendBulk(): void {
        $input = api_request('POST');

        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        $dryRun = (bool) ($input['dry_run'] ?? false);
        $onlyUnsent = (bool) ($input['only_unsent'] ?? true);
        $limit = (int) ($input['limit'] ?? 0);

        global $config;

        $meetingRepo = $this->repo()->meeting();
        $memberRepo = $this->repo()->member();
        $invitationRepo = $this->repo()->invitation();

        $tenantId = api_current_tenant_id();
        $mergedConfig = MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId);
        $meetingTitle = $meetingRepo->findTitle($meetingId, $tenantId) ?? $meetingId;
        $members = $memberRepo->listActiveWithEmail($tenantId);

        if ($limit > 0) {
            $members = array_slice($members, 0, $limit);
        }

        $mailer = new MailerService($mergedConfig);
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
                $st = $invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId, $tenantId);
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

        $response = [
            'meeting_id' => $meetingId,
            'meeting_title' => $meetingTitle,
            'dry_run' => $dryRun,
            'sent' => $sent,
            'skipped' => $skipped,
            'skipped_no_email' => $skippedNoEmail,
            'skipped_already_sent' => $skippedAlreadySent,
            'errors' => $errors,
        ];
        IdempotencyGuard::store($response);
        api_ok($response);
    }

    public function sendReminder(): void {
        $input = api_request('POST');

        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached); }

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }
        api_guard_meeting_not_validated($meetingId);

        global $config;
        $tenantId = api_current_tenant_id();
        $mergedConfig = MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId);
        $service = $this->emailQueueFactory !== null
            ? ($this->emailQueueFactory)($mergedConfig)
            : new EmailQueueService($mergedConfig);
        $result = $service->scheduleReminders($tenantId, $meetingId);

        audit_log('email.reminder', 'meeting', $meetingId, [
            'scheduled' => $result['scheduled'],
        ], $meetingId);

        $response = ['scheduled' => $result['scheduled'], 'errors' => $result['errors']];
        IdempotencyGuard::store($response);
        api_ok($response);
    }

    private function testSmtp(): void {
        global $config;
        $tenantId = api_current_tenant_id();
        $mergedConfig = MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId);
        $mailer = new MailerService($mergedConfig);
        if (!$mailer->isConfigured()) {
            api_fail('smtp_not_configured', 400, ['detail' => 'SMTP host and port are required']);
        }
        $fromEmail = $mergedConfig['smtp']['from_email'] ?? '';
        if ($fromEmail === '') {
            api_fail('smtp_no_sender', 400, ['detail' => 'Sender email (from_email) is required']);
        }
        $result = $mailer->send($fromEmail, 'Test SMTP - AG-VOTE', '<p>Configuration SMTP verifiee avec succes.</p>');
        if (!$result['ok']) {
            api_fail('smtp_test_failed', 400, ['detail' => $result['error']]);
        }
        api_ok(['tested' => true, 'sent_to' => $fromEmail]);
    }
}
