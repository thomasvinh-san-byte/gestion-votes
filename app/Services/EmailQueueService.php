<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\ReminderScheduleRepository;

/**
 * Service for managing the email sending queue.
 * Can be executed as a cron worker.
 */
final class EmailQueueService {
    private EmailQueueRepository $queueRepo;
    private EmailEventRepository $eventRepo;
    private InvitationRepository $invitationRepo;
    private ReminderScheduleRepository $reminderRepo;
    private MemberRepository $memberRepo;
    private MailerService $mailer;
    private EmailTemplateService $templateService;
    private EmailTemplateRepository $emailTemplateRepo;

    public function __construct(
        array $config,
        ?EmailQueueRepository $queueRepo = null,
        ?EmailEventRepository $eventRepo = null,
        ?InvitationRepository $invitationRepo = null,
        ?ReminderScheduleRepository $reminderRepo = null,
        ?MemberRepository $memberRepo = null,
        ?MailerService $mailer = null,
        ?EmailTemplateService $templateService = null,
        ?EmailTemplateRepository $emailTemplateRepo = null,
    ) {
        $this->queueRepo = $queueRepo ?? RepositoryFactory::getInstance()->emailQueue();
        $this->eventRepo = $eventRepo ?? RepositoryFactory::getInstance()->emailEvent();
        $this->invitationRepo = $invitationRepo ?? RepositoryFactory::getInstance()->invitation();
        $this->reminderRepo = $reminderRepo ?? RepositoryFactory::getInstance()->reminderSchedule();
        $this->memberRepo = $memberRepo ?? RepositoryFactory::getInstance()->member();
        $this->mailer = $mailer ?? new MailerService($config);
        $this->templateService = $templateService ?? new EmailTemplateService($config);
        $this->emailTemplateRepo = $emailTemplateRepo ?? RepositoryFactory::getInstance()->emailTemplate();
    }

    /**
     * Processes a batch of emails from the queue.
     *
     * @return array{processed:int,sent:int,failed:int,errors:array}
     */
    public function processQueue(int $batchSize = 25): array {
        $result = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if (!$this->mailer->isConfigured()) {
            return $result;
        }

        // Reset stuck emails
        $this->queueRepo->resetStuckProcessing(30);

        $emails = $this->queueRepo->fetchPendingBatch($batchSize);

        foreach ($emails as $email) {
            $result['processed']++;

            $sendResult = $this->mailer->send(
                $email['recipient_email'],
                $email['subject'],
                $email['body_html'],
                $email['body_text'],
            );

            if ($sendResult['ok']) {
                $this->queueRepo->markSent($email['id'], (string) $email['tenant_id']);

                // Log event
                $this->eventRepo->logEvent(
                    $email['tenant_id'],
                    'sent',
                    $email['invitation_id'],
                    $email['id'],
                );

                // Update invitation status if linked
                if ($email['invitation_id']) {
                    $this->invitationRepo->markSent($email['invitation_id'], (string) $email['tenant_id']);
                }

                $result['sent']++;
            } else {
                $error = $sendResult['error'] ?? 'unknown_error';
                $this->queueRepo->markFailed($email['id'], $error, (string) $email['tenant_id']);

                // Log event
                $this->eventRepo->logEvent(
                    $email['tenant_id'],
                    'failed',
                    $email['invitation_id'],
                    $email['id'],
                    ['error' => $error],
                );

                $result['errors'][] = [
                    'queue_id' => $email['id'],
                    'email' => $email['recipient_email'],
                    'error' => $error,
                ];
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Schedules invitation emails for a meeting.
     */
    public function scheduleInvitations(
        string $tenantId,
        string $meetingId,
        ?string $templateId = null,
        ?string $scheduledAt = null,
        bool $onlyUnsent = true,
    ): array {
        $result = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Default template if not specified
        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo
                ->findDefault($tenantId, 'invitation');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        $offset = 0;
        $batchSize = 25;
        do {
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
            foreach ($members as $member) {
                $memberId = (string) $member['id'];
                $email = trim((string) ($member['email'] ?? ''));

                // Defense-in-depth: skip members from other tenants
                if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
                    $result['skipped']++;
                    continue;
                }

                if ($email === '') {
                    $result['skipped']++;
                    continue;
                }

                // Check if already sent
                if ($onlyUnsent) {
                    $status = $this->invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId, $tenantId);
                    if ($status === 'sent') {
                        $result['skipped']++;
                        continue;
                    }
                }

                // Generate token
                $token = bin2hex(random_bytes(16));

                // Create/update invitation
                $this->invitationRepo->upsertBulk(
                    $tenantId,
                    $meetingId,
                    $memberId,
                    $email,
                    $token,
                    'pending',
                    null,
                );

                // Retrieve invitation ID
                $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId, $tenantId);
                $invitationId = $invitation['id'] ?? null;

                // Rendre le template
                if ($templateId) {
                    $rendered = $this->templateService->renderTemplate(
                        $tenantId,
                        $templateId,
                        $meetingId,
                        $memberId,
                        $token,
                    );
                    if (!$rendered['ok']) {
                        // Fallback to service default template
                        $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                        $subject = $this->templateService->renderHtml('Invitation de vote - {{meeting_title}}', $variables);
                        $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
                    } else {
                        $subject = $rendered['subject'];
                        $bodyHtml = $rendered['body_html'];
                    }
                } else {
                    $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                    $subject = $this->templateService->renderHtml('Invitation de vote - {{meeting_title}}', $variables);
                    $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
                }

                // Add to queue
                $queued = $this->queueRepo->enqueue(
                    $tenantId,
                    $email,
                    $subject,
                    $bodyHtml,
                    null,
                    $scheduledAt,
                    $meetingId,
                    $memberId,
                    $invitationId,
                    $templateId,
                    $member['full_name'] ?? null,
                );

                if ($queued) {
                    // Log event
                    $this->eventRepo->logEvent(
                        $tenantId,
                        'queued',
                        $invitationId,
                        $queued['id'],
                    );
                    $result['scheduled']++;
                } else {
                    $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
                }
            }
            $offset += $batchSize;
        } while (count($members) === $batchSize);

        return $result;
    }

    /**
     * Schedules reminder emails for a meeting (sent to all active members with email).
     */
    public function scheduleReminders(
        string $tenantId,
        string $meetingId,
        ?string $templateId = null,
    ): array {
        $result = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Default reminder template if not specified
        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo
                ->findDefault($tenantId, 'reminder');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        $offset = 0;
        $batchSize = 25;
        do {
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
            foreach ($members as $member) {
                $memberId = (string) $member['id'];
                $email = trim((string) ($member['email'] ?? ''));

                // Defense-in-depth: skip members from other tenants
                if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
                    $result['skipped']++;
                    continue;
                }

                if ($email === '') {
                    $result['skipped']++;
                    continue;
                }

                // Render template
                if ($templateId) {
                    $rendered = $this->templateService->renderTemplate(
                        $tenantId,
                        $templateId,
                        $meetingId,
                        $memberId,
                        '',
                    );
                    if (!$rendered['ok']) {
                        $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, '');
                        $subject = $this->templateService->renderHtml('Rappel : {{meeting_title}}', $variables);
                        $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_REMINDER_TEMPLATE, $variables);
                    } else {
                        $subject = $rendered['subject'];
                        $bodyHtml = $rendered['body_html'];
                    }
                } else {
                    $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, '');
                    $subject = $this->templateService->renderHtml('Rappel : {{meeting_title}}', $variables);
                    $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_REMINDER_TEMPLATE, $variables);
                }

                // Add to queue
                $queued = $this->queueRepo->enqueue(
                    $tenantId,
                    $email,
                    $subject,
                    $bodyHtml,
                    null,
                    null,
                    $meetingId,
                    $memberId,
                    null,
                    $templateId,
                    $member['full_name'] ?? null,
                );

                if ($queued) {
                    $this->eventRepo->logEvent(
                        $tenantId,
                        'queued',
                        null,
                        $queued['id'],
                    );
                    $result['scheduled']++;
                } else {
                    $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
                }
            }
            $offset += $batchSize;
        } while (count($members) === $batchSize);

        return $result;
    }

    /**
     * Schedules results emails for a meeting (fired on session close).
     * Silent no-op when SMTP is not configured.
     */
    public function scheduleResults(
        string $tenantId,
        string $meetingId,
        ?string $templateId = null,
    ): array {
        $result = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Guard: silent return if SMTP not configured
        if (!$this->mailer->isConfigured()) {
            return $result;
        }

        // Default results template if not specified
        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo
                ->findDefault($tenantId, 'results');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        $offset = 0;
        $batchSize = 25;
        do {
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
            foreach ($members as $member) {
                $memberId = (string) $member['id'];
                $email = trim((string) ($member['email'] ?? ''));

                // Defense-in-depth: skip members from other tenants
                if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
                    $result['skipped']++;
                    continue;
                }

                if ($email === '') {
                    $result['skipped']++;
                    continue;
                }

                // Render template (empty token — results emails have no vote token)
                if ($templateId) {
                    $rendered = $this->templateService->renderTemplate(
                        $tenantId,
                        $templateId,
                        $meetingId,
                        $memberId,
                        '',
                    );
                    if (!$rendered['ok']) {
                        $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, '');
                        $subject = $this->templateService->renderHtml('Resultats de la seance - {{meeting_title}}', $variables);
                        $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_RESULTS_TEMPLATE, $variables);
                    } else {
                        $subject = $rendered['subject'];
                        $bodyHtml = $rendered['body_html'];
                    }
                } else {
                    $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, '');
                    $subject = $this->templateService->renderHtml('Resultats de la seance - {{meeting_title}}', $variables);
                    $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_RESULTS_TEMPLATE, $variables);
                }

                // Add to queue (no invitation_id for results emails)
                $queued = $this->queueRepo->enqueue(
                    $tenantId,
                    $email,
                    $subject,
                    $bodyHtml,
                    null,
                    null,
                    $meetingId,
                    $memberId,
                    null,
                    $templateId,
                    $member['full_name'] ?? null,
                );

                if ($queued) {
                    $this->eventRepo->logEvent(
                        $tenantId,
                        'queued',
                        null,
                        $queued['id'],
                    );
                    $result['scheduled']++;
                } else {
                    $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
                }
            }
            $offset += $batchSize;
        } while (count($members) === $batchSize);

        return $result;
    }

    /**
     * Processes scheduled reminders.
     */
    public function processReminders(): array {
        $result = [
            'processed' => 0,
            'sent' => 0,
            'errors' => [],
        ];

        $dueReminders = $this->reminderRepo->findDueReminders();

        foreach ($dueReminders as $reminder) {
            $result['processed']++;

            // Schedule emails for this meeting
            $scheduled = $this->scheduleInvitations(
                $reminder['tenant_id'],
                $reminder['meeting_id'],
                $reminder['template_id'],
                null, // Immediate send
                false, // Send to all (reminder)
            );

            $result['sent'] += $scheduled['scheduled'];
            $result['errors'] = array_merge($result['errors'], $scheduled['errors']);

            // Mark as executed
            $this->reminderRepo->markExecuted($reminder['id'], (string) $reminder['tenant_id']);
        }

        return $result;
    }

    /**
     * Immediate invitation sending (bypassing queue).
     */
    public function sendInvitationsNow(
        string $tenantId,
        string $meetingId,
        ?string $templateId = null,
        bool $onlyUnsent = true,
        int $limit = 0,
    ): array {
        $result = [
            'sent' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!$this->mailer->isConfigured()) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => [['error' => 'smtp_not_configured']]];
        }

        if ($limit > 0) {
            // Single paginated batch for limited sends
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $limit, 0);
            $this->sendInvitationsNowBatch($members, $tenantId, $meetingId, $templateId, $onlyUnsent, $result);
        } else {
            // Full paginated iteration
            $offset = 0;
            $batchSize = 25;
            do {
                $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
                $this->sendInvitationsNowBatch($members, $tenantId, $meetingId, $templateId, $onlyUnsent, $result);
                $offset += $batchSize;
            } while (count($members) === $batchSize);
        }

        return $result;
    }

    /**
     * Processes a batch of members for sendInvitationsNow().
     */
    private function sendInvitationsNowBatch(
        array $members,
        string $tenantId,
        string $meetingId,
        ?string $templateId,
        bool $onlyUnsent,
        array &$result,
    ): void {
        foreach ($members as $member) {
            $memberId = (string) $member['id'];
            $email = trim((string) ($member['email'] ?? ''));

            // Defense-in-depth: skip members from other tenants
            if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
                $result['skipped']++;
                continue;
            }

            if ($email === '') {
                $result['skipped']++;
                continue;
            }

            if ($onlyUnsent) {
                $status = $this->invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId, $tenantId);
                if ($status === 'sent') {
                    $result['skipped']++;
                    continue;
                }
            }

            $token = bin2hex(random_bytes(16));

            // Rendre le template
            if ($templateId) {
                $rendered = $this->templateService->renderTemplate(
                    $tenantId,
                    $templateId,
                    $meetingId,
                    $memberId,
                    $token,
                );
                $subject = $rendered['ok'] ? $rendered['subject'] : 'Invitation de vote';
                $bodyHtml = $rendered['ok'] ? $rendered['body_html'] : '';
            } else {
                $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                $subject = $this->templateService->renderHtml('Invitation de vote - {{meeting_title}}', $variables);
                $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
            }

            // Send
            $sendResult = $this->mailer->send($email, $subject, $bodyHtml);

            if ($sendResult['ok']) {
                $this->invitationRepo->upsertBulk(
                    $tenantId,
                    $meetingId,
                    $memberId,
                    $email,
                    $token,
                    'sent',
                    date('c'),
                );

                $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId, $tenantId);
                if ($invitation) {
                    $this->eventRepo->logEvent($tenantId, 'sent', $invitation['id']);
                }

                $result['sent']++;
            } else {
                $this->invitationRepo->markBounced($meetingId, $memberId, $tenantId);
                $result['errors'][] = [
                    'member_id' => $memberId,
                    'email' => $email,
                    'error' => $sendResult['error'] ?? 'unknown',
                ];
            }
        }
    }

    /**
     * Queue statistics.
     */
    public function getQueueStats(string $tenantId): array {
        return $this->queueRepo->getQueueStats($tenantId);
    }

    /**
     * Cancels all scheduled emails for a meeting.
     */
    public function cancelMeetingEmails(string $meetingId, string $tenantId): int {
        return $this->queueRepo->cancelForMeeting($meetingId, $tenantId);
    }

    /**
     * Cleans up old emails.
     */
    public function cleanup(int $daysToKeep = 30): int {
        return $this->queueRepo->cleanupOld($daysToKeep);
    }
}
