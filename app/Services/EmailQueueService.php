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
    private array $config;
    private ?RetryPolicy $retryPolicy = null;

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
        ?RetryPolicy $retryPolicy = null,
    ) {
        $this->config = $config;
        $this->queueRepo = $queueRepo ?? RepositoryFactory::getInstance()->emailQueue();
        $this->eventRepo = $eventRepo ?? RepositoryFactory::getInstance()->emailEvent();
        $this->invitationRepo = $invitationRepo ?? RepositoryFactory::getInstance()->invitation();
        $this->reminderRepo = $reminderRepo ?? RepositoryFactory::getInstance()->reminderSchedule();
        $this->memberRepo = $memberRepo ?? RepositoryFactory::getInstance()->member();
        $this->mailer = $mailer ?? new MailerService($config);
        $this->templateService = $templateService ?? new EmailTemplateService($config);
        $this->emailTemplateRepo = $emailTemplateRepo ?? RepositoryFactory::getInstance()->emailTemplate();
        $this->retryPolicy = $retryPolicy;
    }

    private function retryPolicy(): RetryPolicy {
        return $this->retryPolicy ??= new RetryPolicy(
            $this->config,
            $this->queueRepo,
            $this->eventRepo,
            $this->invitationRepo,
            $this->memberRepo,
            $this->mailer,
            $this->templateService,
        );
    }

    /**
     * Processes a batch of emails from the queue.
     *
     * @return array{processed:int,sent:int,failed:int,errors:array}
     */
    public function processQueue(int $batchSize = 25): array {
        return $this->retryPolicy()->processBatch($batchSize);
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
        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo->findDefault($tenantId, 'invitation');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        return $this->retryPolicy()->scheduleForMembers(
            $tenantId, $meetingId, $templateId, $scheduledAt,
            'Invitation de vote - {{meeting_title}}',
            EmailTemplateService::DEFAULT_INVITATION_TEMPLATE,
            checkUnsent: $onlyUnsent,
            createInvitation: true,
        );
    }

    /**
     * Schedules reminder emails for a meeting (sent to all active members with email).
     */
    public function scheduleReminders(
        string $tenantId,
        string $meetingId,
        ?string $templateId = null,
    ): array {
        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo->findDefault($tenantId, 'reminder');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        return $this->retryPolicy()->scheduleForMembers(
            $tenantId, $meetingId, $templateId, null,
            'Rappel : {{meeting_title}}',
            EmailTemplateService::DEFAULT_REMINDER_TEMPLATE,
        );
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
        $result = ['scheduled' => 0, 'skipped' => 0, 'errors' => []];

        if (!$this->mailer->isConfigured()) {
            return $result;
        }

        if (!$templateId) {
            $defaultTemplate = $this->emailTemplateRepo->findDefault($tenantId, 'results');
            $templateId = $defaultTemplate['id'] ?? null;
        }

        return $this->retryPolicy()->scheduleForMembers(
            $tenantId, $meetingId, $templateId, null,
            'Resultats de la seance - {{meeting_title}}',
            EmailTemplateService::DEFAULT_RESULTS_TEMPLATE,
        );
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

            $scheduled = $this->scheduleInvitations(
                $reminder['tenant_id'],
                $reminder['meeting_id'],
                $reminder['template_id'],
                null,
                false,
            );

            $result['sent'] += $scheduled['scheduled'];
            $result['errors'] = array_merge($result['errors'], $scheduled['errors']);

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
        return $this->retryPolicy()->sendImmediate($tenantId, $meetingId, $templateId, $onlyUnsent, $limit);
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
