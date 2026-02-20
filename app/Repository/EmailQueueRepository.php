<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour la file d'attente des emails.
 */
class EmailQueueRepository extends AbstractRepository
{
    /**
     * Ajoute un email a la file d'attente.
     */
    public function enqueue(
        string $tenantId,
        string $recipientEmail,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $scheduledAt = null,
        ?string $meetingId = null,
        ?string $memberId = null,
        ?string $invitationId = null,
        ?string $templateId = null,
        ?string $recipientName = null,
        int $priority = 0
    ): ?array {
        $scheduledAt = $scheduledAt ?? date('c');

        return $this->insertReturning(
            "INSERT INTO email_queue
             (tenant_id, meeting_id, member_id, invitation_id, template_id,
              recipient_email, recipient_name, subject, body_html, body_text, scheduled_at, priority)
             VALUES (:tenant_id, :meeting_id, :member_id, :invitation_id, :template_id,
                     :recipient_email, :recipient_name, :subject, :body_html, :body_text, :scheduled_at, :priority)
             RETURNING id, tenant_id, status, scheduled_at, created_at",
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':member_id' => $memberId,
                ':invitation_id' => $invitationId,
                ':template_id' => $templateId,
                ':recipient_email' => $recipientEmail,
                ':recipient_name' => $recipientName,
                ':subject' => $subject,
                ':body_html' => $bodyHtml,
                ':body_text' => $bodyText,
                ':scheduled_at' => $scheduledAt,
                ':priority' => $priority,
            ]
        );
    }

    /**
     * Recupere les emails a envoyer (pending et scheduled_at <= now).
     */
    public function fetchPendingBatch(int $batchSize = 50): array
    {
        return $this->selectAll(
            "UPDATE email_queue
             SET status = 'processing', updated_at = now()
             WHERE id IN (
                 SELECT id FROM email_queue
                 WHERE status = 'pending'
                   AND scheduled_at <= now()
                   AND (retry_count < max_retries OR retry_count = 0)
                 ORDER BY priority DESC, scheduled_at ASC
                 LIMIT :batch_size
                 FOR UPDATE SKIP LOCKED
             )
             RETURNING id, tenant_id, meeting_id, member_id, invitation_id, template_id,
                       recipient_email, recipient_name, subject, body_html, body_text,
                       scheduled_at, retry_count, created_at",
            [':batch_size' => $batchSize]
        );
    }

    /**
     * Marque un email comme envoye.
     */
    public function markSent(string $id): void
    {
        $this->execute(
            "UPDATE email_queue SET status = 'sent', sent_at = now(), updated_at = now()
             WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Marque un email comme echoue (avec retry si possible).
     */
    public function markFailed(string $id, string $error): void
    {
        $this->execute(
            "UPDATE email_queue
             SET status = CASE
                     WHEN retry_count + 1 >= max_retries THEN 'failed'
                     ELSE 'pending'
                 END,
                 retry_count = retry_count + 1,
                 last_error = :error,
                 scheduled_at = CASE
                     WHEN retry_count + 1 < max_retries THEN now() + interval '5 minutes' * power(2, retry_count)
                     ELSE scheduled_at
                 END,
                 updated_at = now()
             WHERE id = :id",
            [':id' => $id, ':error' => $error]
        );
    }

    /**
     * Annule tous les emails programmes pour une seance.
     */
    public function cancelForMeeting(string $meetingId, string $tenantId = ''): int
    {
        if ($tenantId !== '') {
            return $this->execute(
                "UPDATE email_queue SET status = 'cancelled', updated_at = now()
                 WHERE meeting_id = :meeting_id AND tenant_id = :tid AND status = 'pending'",
                [':meeting_id' => $meetingId, ':tid' => $tenantId]
            );
        }
        return $this->execute(
            "UPDATE email_queue SET status = 'cancelled', updated_at = now()
             WHERE meeting_id = :meeting_id AND status = 'pending'",
            [':meeting_id' => $meetingId]
        );
    }

    /**
     * Liste les emails en file pour une seance.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT eq.id, eq.recipient_email, eq.recipient_name, eq.subject,
                    eq.status, eq.scheduled_at, eq.sent_at, eq.retry_count, eq.last_error,
                    m.full_name as member_name
             FROM email_queue eq
             LEFT JOIN members m ON m.id = eq.member_id
             WHERE eq.meeting_id = :meeting_id AND eq.tenant_id = :tenant_id
             ORDER BY eq.scheduled_at DESC",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Compte les emails par statut pour une seance.
     */
    public function countByStatusForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT status, COUNT(*) as count
             FROM email_queue
             WHERE meeting_id = :meeting_id
             GROUP BY status",
            [':meeting_id' => $meetingId]
        );
    }

    /**
     * Statistiques globales de la file.
     */
    public function getQueueStats(string $tenantId): array
    {
        $row = $this->selectOne(
            "SELECT
                 COUNT(*) as total,
                 COUNT(*) FILTER (WHERE status = 'pending') as pending,
                 COUNT(*) FILTER (WHERE status = 'processing') as processing,
                 COUNT(*) FILTER (WHERE status = 'sent') as sent,
                 COUNT(*) FILTER (WHERE status = 'failed') as failed,
                 COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled
             FROM email_queue
             WHERE tenant_id = :tenant_id
               AND created_at > now() - interval '7 days'",
            [':tenant_id' => $tenantId]
        );
        return $row ?: ['total' => 0, 'pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    }

    /**
     * Nettoie les anciens emails envoyes (retention).
     */
    public function cleanupOld(int $daysToKeep = 30): int
    {
        return $this->execute(
            "DELETE FROM email_queue
             WHERE status IN ('sent', 'cancelled', 'failed')
               AND created_at < now() - interval '1 day' * :days",
            [':days' => $daysToKeep]
        );
    }

    /**
     * Reset les emails bloques en 'processing' (timeout).
     */
    public function resetStuckProcessing(int $timeoutMinutes = 30): int
    {
        return $this->execute(
            "UPDATE email_queue
             SET status = 'pending', updated_at = now()
             WHERE status = 'processing'
               AND updated_at < now() - interval '1 minute' * :timeout",
            [':timeout' => $timeoutMinutes]
        );
    }
}
