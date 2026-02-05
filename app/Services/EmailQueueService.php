<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\ReminderScheduleRepository;
use AgVote\Repository\MemberRepository;

/**
 * Service for managing the email sending queue.
 * Can be executed as a cron worker.
 */
final class EmailQueueService
{
    private EmailQueueRepository $queueRepo;
    private EmailEventRepository $eventRepo;
    private InvitationRepository $invitationRepo;
    private ReminderScheduleRepository $reminderRepo;
    private MemberRepository $memberRepo;
    private MailerService $mailer;
    private EmailTemplateService $templateService;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->queueRepo = new EmailQueueRepository();
        $this->eventRepo = new EmailEventRepository();
        $this->invitationRepo = new InvitationRepository();
        $this->reminderRepo = new ReminderScheduleRepository();
        $this->memberRepo = new MemberRepository();
        $this->mailer = new MailerService($config);
        $this->templateService = new EmailTemplateService($config);
    }

    /**
     * Processes a batch of emails from the queue.
     *
     * @return array{processed:int,sent:int,failed:int,errors:array}
     */
    public function processQueue(int $batchSize = 50): array
    {
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
                $email['body_text']
            );

            if ($sendResult['ok']) {
                $this->queueRepo->markSent($email['id']);

                // Log event
                $this->eventRepo->logEvent(
                    $email['tenant_id'],
                    'sent',
                    $email['invitation_id'],
                    $email['id']
                );

                // Update invitation status if linked
                if ($email['invitation_id']) {
                    $this->invitationRepo->markSent($email['invitation_id']);
                }

                $result['sent']++;
            } else {
                $error = $sendResult['error'] ?? 'unknown_error';
                $this->queueRepo->markFailed($email['id'], $error);

                // Log event
                $this->eventRepo->logEvent(
                    $email['tenant_id'],
                    'failed',
                    $email['invitation_id'],
                    $email['id'],
                    ['error' => $error]
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
        bool $onlyUnsent = true
    ): array {
        $result = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Active members with email
        $members = $this->memberRepo->listActiveWithEmail($tenantId);

        // Default template if not specified
        if (!$templateId) {
            $defaultTemplate = (new \AgVote\Repository\EmailTemplateRepository())
                ->findDefault($tenantId, 'invitation');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        foreach ($members as $member) {
            $memberId = (string)$member['id'];
            $email = trim((string)($member['email'] ?? ''));

            if ($email === '') {
                $result['skipped']++;
                continue;
            }

            // Check if already sent
            if ($onlyUnsent) {
                $status = $this->invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId);
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
                null
            );

            // Retrieve invitation ID
            $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId);
            $invitationId = $invitation['id'] ?? null;

            // Rendre le template
            if ($templateId) {
                $rendered = $this->templateService->renderTemplate(
                    $tenantId,
                    $templateId,
                    $meetingId,
                    $memberId,
                    $token
                );
                if (!$rendered['ok']) {
                    // Fallback to service default template
                    $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                    $subject = $this->templateService->render('Invitation de vote - {{meeting_title}}', $variables);
                    $bodyHtml = $this->templateService->render(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
                } else {
                    $subject = $rendered['subject'];
                    $bodyHtml = $rendered['body_html'];
                }
            } else {
                $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                $subject = $this->templateService->render('Invitation de vote - {{meeting_title}}', $variables);
                $bodyHtml = $this->templateService->render(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
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
                $member['full_name'] ?? null
            );

            if ($queued) {
                // Log event
                $this->eventRepo->logEvent(
                    $tenantId,
                    'queued',
                    $invitationId,
                    $queued['id']
                );
                $result['scheduled']++;
            } else {
                $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
            }
        }

        return $result;
    }

    /**
     * Processes scheduled reminders.
     */
    public function processReminders(): array
    {
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
                false // Send to all (reminder)
            );

            $result['sent'] += $scheduled['scheduled'];
            $result['errors'] = array_merge($result['errors'], $scheduled['errors']);

            // Mark as executed
            $this->reminderRepo->markExecuted($reminder['id']);
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
        int $limit = 0
    ): array {
        $result = [
            'sent' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!$this->mailer->isConfigured()) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => [['error' => 'smtp_not_configured']]];
        }

        $members = $this->memberRepo->listActiveWithEmail($tenantId);
        if ($limit > 0) {
            $members = array_slice($members, 0, $limit);
        }

        foreach ($members as $member) {
            $memberId = (string)$member['id'];
            $email = trim((string)($member['email'] ?? ''));

            if ($email === '') {
                $result['skipped']++;
                continue;
            }

            if ($onlyUnsent) {
                $status = $this->invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId);
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
                    $token
                );
                $subject = $rendered['ok'] ? $rendered['subject'] : "Invitation de vote";
                $bodyHtml = $rendered['ok'] ? $rendered['body_html'] : '';
            } else {
                $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                $subject = $this->templateService->render('Invitation de vote - {{meeting_title}}', $variables);
                $bodyHtml = $this->templateService->render(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
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
                    date('c')
                );

                $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId);
                if ($invitation) {
                    $this->eventRepo->logEvent($tenantId, 'sent', $invitation['id']);
                }

                $result['sent']++;
            } else {
                $this->invitationRepo->markBounced($meetingId, $memberId);
                $result['errors'][] = [
                    'member_id' => $memberId,
                    'email' => $email,
                    'error' => $sendResult['error'] ?? 'unknown',
                ];
            }
        }

        return $result;
    }

    /**
     * Queue statistics.
     */
    public function getQueueStats(string $tenantId): array
    {
        return $this->queueRepo->getQueueStats($tenantId);
    }

    /**
     * Cancels all scheduled emails for a meeting.
     */
    public function cancelMeetingEmails(string $meetingId): int
    {
        return $this->queueRepo->cancelForMeeting($meetingId);
    }

    /**
     * Cleans up old emails.
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        return $this->queueRepo->cleanupOld($daysToKeep);
    }
}
