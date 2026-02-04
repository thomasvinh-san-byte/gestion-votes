<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les evenements email (tracking).
 */
class EmailEventRepository extends AbstractRepository
{
    /**
     * Enregistre un evenement email.
     */
    public function logEvent(
        string $tenantId,
        string $eventType,
        ?string $invitationId = null,
        ?string $queueId = null,
        ?array $eventData = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?array {
        return $this->insertReturning(
            "INSERT INTO email_events
             (tenant_id, invitation_id, queue_id, event_type, event_data, ip_address, user_agent)
             VALUES (:tenant_id, :invitation_id, :queue_id, :event_type, :event_data, :ip_address, :user_agent)
             RETURNING id, event_type, created_at",
            [
                ':tenant_id' => $tenantId,
                ':invitation_id' => $invitationId,
                ':queue_id' => $queueId,
                ':event_type' => $eventType,
                ':event_data' => $eventData ? json_encode($eventData) : '{}',
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]
        );
    }

    /**
     * Liste les evenements pour une invitation.
     */
    public function listForInvitation(string $invitationId): array
    {
        return $this->selectAll(
            "SELECT id, event_type, event_data, ip_address, user_agent, created_at
             FROM email_events
             WHERE invitation_id = :invitation_id
             ORDER BY created_at ASC",
            [':invitation_id' => $invitationId]
        );
    }

    /**
     * Liste les evenements recents pour un tenant.
     */
    public function listRecent(string $tenantId, int $limit = 100): array
    {
        return $this->selectAll(
            "SELECT ee.id, ee.invitation_id, ee.queue_id, ee.event_type, ee.event_data,
                    ee.ip_address, ee.created_at,
                    i.email as invitation_email, m.full_name as member_name, mt.title as meeting_title
             FROM email_events ee
             LEFT JOIN invitations i ON i.id = ee.invitation_id
             LEFT JOIN members m ON m.id = i.member_id
             LEFT JOIN meetings mt ON mt.id = i.meeting_id
             WHERE ee.tenant_id = :tenant_id
             ORDER BY ee.created_at DESC
             LIMIT :limit",
            [':tenant_id' => $tenantId, ':limit' => $limit]
        );
    }

    /**
     * Compte les evenements par type pour une seance.
     */
    public function countByTypeForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT ee.event_type, COUNT(*) as count
             FROM email_events ee
             JOIN invitations i ON i.id = ee.invitation_id
             WHERE i.meeting_id = :meeting_id
             GROUP BY ee.event_type",
            [':meeting_id' => $meetingId]
        );
    }

    /**
     * Statistiques detaillees pour une seance.
     */
    public function getStatsForMeeting(string $meetingId, string $tenantId): array
    {
        $row = $this->selectOne(
            "SELECT * FROM email_stats_by_meeting
             WHERE meeting_id = :meeting_id AND tenant_id = :tenant_id",
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId]
        );

        return $row ?: [
            'total_invitations' => 0,
            'pending_count' => 0,
            'sent_count' => 0,
            'opened_count' => 0,
            'accepted_count' => 0,
            'declined_count' => 0,
            'bounced_count' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
            'open_rate' => 0,
            'bounce_rate' => 0,
        ];
    }
}
