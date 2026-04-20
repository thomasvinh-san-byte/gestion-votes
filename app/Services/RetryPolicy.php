<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MemberRepository;

final class RetryPolicy {
    private EmailQueueRepository $queueRepo;
    private EmailEventRepository $eventRepo;
    private InvitationRepository $invitationRepo;
    private MemberRepository $memberRepo;
    private MailerService $mailer;
    private EmailTemplateService $templateService;

    public function __construct(
        array $config,
        ?EmailQueueRepository $queueRepo = null,
        ?EmailEventRepository $eventRepo = null,
        ?InvitationRepository $invitationRepo = null,
        ?MemberRepository $memberRepo = null,
        ?MailerService $mailer = null,
        ?EmailTemplateService $templateService = null,
    ) {
        $this->queueRepo = $queueRepo ?? RepositoryFactory::getInstance()->emailQueue();
        $this->eventRepo = $eventRepo ?? RepositoryFactory::getInstance()->emailEvent();
        $this->invitationRepo = $invitationRepo ?? RepositoryFactory::getInstance()->invitation();
        $this->memberRepo = $memberRepo ?? RepositoryFactory::getInstance()->member();
        $this->mailer = $mailer ?? new MailerService($config);
        $this->templateService = $templateService ?? new EmailTemplateService($config);
    }

    /** @return array{processed:int,sent:int,failed:int,errors:array} */
    public function processBatch(int $batchSize = 25): array {
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'errors' => []];

        if (!$this->mailer->isConfigured()) {
            return $result;
        }

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
                $this->eventRepo->logEvent($email['tenant_id'], 'sent', $email['invitation_id'], $email['id']);

                if ($email['invitation_id']) {
                    $this->invitationRepo->markSent($email['invitation_id'], (string) $email['tenant_id']);
                }

                $result['sent']++;
            } else {
                $error = $sendResult['error'] ?? 'unknown_error';
                $this->queueRepo->markFailed($email['id'], $error, (string) $email['tenant_id']);
                $this->eventRepo->logEvent($email['tenant_id'], 'failed', $email['invitation_id'], $email['id'], ['error' => $error]);
                $result['errors'][] = ['queue_id' => $email['id'], 'email' => $email['recipient_email'], 'error' => $error];
                $result['failed']++;
            }
        }

        return $result;
    }

    /** @return array{scheduled:int,skipped:int,errors:array} */
    public function scheduleForMembers(
        string $tenantId,
        string $meetingId,
        ?string $templateId,
        ?string $scheduledAt,
        string $defaultSubject,
        string $defaultBodyConstant,
        bool $checkUnsent = false,
        bool $createInvitation = false,
    ): array {
        $result = ['scheduled' => 0, 'skipped' => 0, 'errors' => []];
        $offset = 0;
        $batchSize = 25;

        do {
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
            foreach ($members as $member) {
                $memberId = (string) $member['id'];
                $email = trim((string) ($member['email'] ?? ''));

                if (isset($member['tenant_id']) && (string) $member['tenant_id'] !== $tenantId) {
                    $result['skipped']++;
                    continue;
                }

                if ($email === '') {
                    $result['skipped']++;
                    continue;
                }

                if ($checkUnsent) {
                    $status = $this->invitationRepo->findStatusByMeetingAndMember($meetingId, $memberId, $tenantId);
                    if ($status === 'sent') {
                        $result['skipped']++;
                        continue;
                    }
                }

                $token = $createInvitation ? bin2hex(random_bytes(16)) : '';
                $invitationId = null;

                if ($createInvitation) {
                    $this->invitationRepo->upsertBulk($tenantId, $meetingId, $memberId, $email, $token, 'pending', null);
                    $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId, $tenantId);
                    $invitationId = $invitation['id'] ?? null;
                }

                [$subject, $bodyHtml] = $this->renderEmail($tenantId, $templateId, $meetingId, $memberId, $token, $defaultSubject, $defaultBodyConstant);

                $queued = $this->queueRepo->enqueue(
                    $tenantId, $email, $subject, $bodyHtml, null, $scheduledAt,
                    $meetingId, $memberId, $invitationId, $templateId, $member['full_name'] ?? null,
                );

                if ($queued) {
                    $this->eventRepo->logEvent($tenantId, 'queued', $invitationId, $queued['id']);
                    $result['scheduled']++;
                } else {
                    $result['errors'][] = ['member_id' => $memberId, 'error' => 'queue_insert_failed'];
                }
            }
            $offset += $batchSize;
        } while (count($members) === $batchSize);

        return $result;
    }

    /** @return array{sent:int,skipped:int,errors:array} */
    public function sendImmediate(
        string $tenantId,
        string $meetingId,
        ?string $templateId,
        bool $onlyUnsent,
        int $limit,
    ): array {
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];

        if (!$this->mailer->isConfigured()) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => [['error' => 'smtp_not_configured']]];
        }

        if ($limit > 0) {
            $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $limit, 0);
            $this->sendImmediateBatch($members, $tenantId, $meetingId, $templateId, $onlyUnsent, $result);
        } else {
            $offset = 0;
            $batchSize = 25;
            do {
                $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
                $this->sendImmediateBatch($members, $tenantId, $meetingId, $templateId, $onlyUnsent, $result);
                $offset += $batchSize;
            } while (count($members) === $batchSize);
        }

        return $result;
    }

    private function sendImmediateBatch(
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

            if ($templateId) {
                $rendered = $this->templateService->renderTemplate($tenantId, $templateId, $meetingId, $memberId, $token);
                $subject = $rendered['ok'] ? $rendered['subject'] : 'Invitation de vote';
                $bodyHtml = $rendered['ok'] ? $rendered['body_html'] : '';
            } else {
                $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
                $subject = $this->templateService->renderHtml('Invitation de vote - {{meeting_title}}', $variables);
                $bodyHtml = $this->templateService->renderHtml(EmailTemplateService::DEFAULT_INVITATION_TEMPLATE, $variables);
            }

            $sendResult = $this->mailer->send($email, $subject, $bodyHtml);

            if ($sendResult['ok']) {
                $this->invitationRepo->upsertBulk($tenantId, $meetingId, $memberId, $email, $token, 'sent', date('c'));
                $invitation = $this->invitationRepo->findByMeetingAndMember($meetingId, $memberId, $tenantId);
                if ($invitation) {
                    $this->eventRepo->logEvent($tenantId, 'sent', $invitation['id']);
                }
                $result['sent']++;
            } else {
                $this->invitationRepo->markBounced($meetingId, $memberId, $tenantId);
                $result['errors'][] = ['member_id' => $memberId, 'email' => $email, 'error' => $sendResult['error'] ?? 'unknown'];
            }
        }
    }

    /** @return array{string, string} [subject, bodyHtml] */
    private function renderEmail(
        string $tenantId,
        ?string $templateId,
        string $meetingId,
        string $memberId,
        string $token,
        string $defaultSubject,
        string $defaultBodyConstant,
    ): array {
        if ($templateId) {
            $rendered = $this->templateService->renderTemplate($tenantId, $templateId, $meetingId, $memberId, $token);
            if ($rendered['ok']) {
                return [$rendered['subject'], $rendered['body_html']];
            }
        }

        $variables = $this->templateService->getVariables($tenantId, $meetingId, $memberId, $token);
        $subject = $this->templateService->renderHtml($defaultSubject, $variables);
        $bodyHtml = $this->templateService->renderHtml($defaultBodyConstant, $variables);

        return [$subject, $bodyHtml];
    }
}
