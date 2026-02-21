<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for audit events.
 *
 * Centralizes all audit_events queries that were previously
 * inlined in endpoint files (admin_audit_log.php, etc.).
 */
class AuditEventRepository extends AbstractRepository
{
    /**
     * Insert an audit event.
     */
    public function insert(
        ?string $tenantId,
        ?string $meetingId,
        ?string $actorUserId,
        ?string $actorRole,
        string $action,
        string $resourceType,
        ?string $resourceId,
        array $payload
    ): void {
        $this->execute(
            "INSERT INTO audit_events
                (tenant_id, meeting_id, actor_user_id, actor_role, action, resource_type, resource_id, payload, created_at)
                VALUES (:tid, :mid, :uid, :role, :action, :rtype, :rid, :payload::jsonb, NOW())",
            [
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':uid' => $actorUserId,
                ':role' => $actorRole,
                ':action' => $action,
                ':rtype' => $resourceType,
                ':rid' => $resourceId,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * Searches admin audit events with optional filters.
     *
     * @return array List of audit event rows
     */
    public function searchAdminEvents(
        string $tenantId,
        ?string $action = null,
        ?string $query = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $where = "WHERE tenant_id = ?";
        $params = [$tenantId];

        $where .= " AND (action LIKE 'admin.%' OR action LIKE 'admin\\_%')";

        if ($action !== null && $action !== '') {
            $where .= " AND action = ?";
            $params[] = $action;
        }

        if ($query !== null && $query !== '') {
            $where .= " AND (action ILIKE ? OR CAST(payload AS text) ILIKE ? OR actor_role ILIKE ?)";
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT id, action, resource_type, resource_id, actor_user_id, actor_role,
                       payload, ip_address, created_at
                FROM audit_events
                $where
                ORDER BY created_at DESC
                LIMIT $limit OFFSET $offset";

        return $this->selectAll($sql, $params);
    }

    /**
     * Counts admin audit events matching filters.
     */
    public function countAdminEvents(
        string $tenantId,
        ?string $action = null,
        ?string $query = null
    ): int {
        $where = "WHERE tenant_id = ?";
        $params = [$tenantId];

        $where .= " AND (action LIKE 'admin.%' OR action LIKE 'admin\\_%')";

        if ($action !== null && $action !== '') {
            $where .= " AND action = ?";
            $params[] = $action;
        }

        if ($query !== null && $query !== '') {
            $where .= " AND (action ILIKE ? OR CAST(payload AS text) ILIKE ? OR actor_role ILIKE ?)";
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return (int)($this->scalar("SELECT COUNT(*) FROM audit_events $where", $params) ?? 0);
    }

    /**
     * Lists distinct admin action types for filter dropdown.
     */
    public function listDistinctAdminActions(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT DISTINCT action FROM audit_events
             WHERE tenant_id = ? AND (action LIKE 'admin.%' OR action LIKE 'admin\\_%')
             ORDER BY action",
            [$tenantId]
        );
    }

    // =========================================================================
    // MEETING AUDIT EVENTS (migrated from MeetingRepository)
    // =========================================================================

    /**
     * Audit events for CSV export (all columns, chronological).
     */
    public function listForMeetingExport(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT created_at, actor_role, actor_user_id, action, resource_type, resource_id, payload, prev_hash, this_hash
             FROM audit_events
             WHERE tenant_id = :tid AND meeting_id = :mid
             ORDER BY created_at ASC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Audit events for a meeting.
     */
    public function listForMeeting(string $meetingId, string $tenantId, int $limit = 200, string $order = 'DESC'): array
    {
        $order = ($order === 'ASC') ? 'ASC' : 'DESC';
        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             WHERE tenant_id = :tid
               AND ((resource_type = 'meeting' AND resource_id = :mid)
                    OR (resource_type = 'motion' AND resource_id IN (
                        SELECT id FROM motions WHERE meeting_id = :mid2)))
             ORDER BY created_at {$order}
             LIMIT " . max(1, $limit),
            [':tid' => $tenantId, ':mid' => $meetingId, ':mid2' => $meetingId]
        );
    }

    /**
     * Filtered audit events for a meeting (for operator_audit_events).
     */
    public function listForMeetingFiltered(
        string $tenantId,
        string $meetingId,
        int $limit = 200,
        string $resourceType = '',
        string $action = '',
        string $q = ''
    ): array {
        $where = "WHERE tenant_id = ? AND (
            (resource_type = 'meeting' AND resource_id = ?)
            OR
            (resource_type = 'motion' AND resource_id IN (SELECT id FROM motions WHERE meeting_id = ?))
        )";
        $params = [$tenantId, $meetingId, $meetingId];

        if ($resourceType !== '') {
            $where .= " AND resource_type = ?";
            $params[] = $resourceType;
        }
        if ($action !== '') {
            $where .= " AND action ILIKE ?";
            $params[] = "%" . $action . "%";
        }
        if ($q !== '') {
            $where .= " AND (action ILIKE ? OR resource_id ILIKE ? OR payload::text ILIKE ?)";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
        }

        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             {$where}
             ORDER BY created_at DESC
             LIMIT " . max(1, min($limit, 500)),
            $params
        );
    }

    /**
     * Paginated audit events for the audit log (timeline).
     */
    public function listForMeetingLog(
        string $tenantId,
        string $meetingId,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->selectAll(
            "SELECT
                ae.id,
                ae.action,
                ae.resource_type,
                ae.resource_id,
                ae.actor_user_id,
                ae.actor_role,
                ae.payload,
                ae.ip_address,
                ae.created_at
            FROM audit_events ae
            WHERE ae.tenant_id = ?
              AND (
                ae.meeting_id = ?
                OR (ae.resource_type = 'meeting' AND ae.resource_id = ?)
                OR (ae.resource_type = 'motion' AND ae.resource_id IN (
                    SELECT id FROM motions WHERE meeting_id = ?
                ))
                OR (ae.resource_type = 'attendance' AND ae.resource_id IN (
                    SELECT id FROM attendances WHERE meeting_id = ?
                ))
              )
            ORDER BY ae.created_at DESC
            LIMIT ? OFFSET ?",
            [$tenantId, $meetingId, $meetingId, $meetingId, $meetingId, $limit, $offset]
        );
    }

    /**
     * Count total audit events for the audit log (pagination).
     */
    public function countForMeetingLog(string $tenantId, string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
            FROM audit_events ae
            WHERE ae.tenant_id = ?
              AND (
                ae.meeting_id = ?
                OR (ae.resource_type = 'meeting' AND ae.resource_id = ?)
                OR (ae.resource_type = 'motion' AND ae.resource_id IN (
                    SELECT id FROM motions WHERE meeting_id = ?
                ))
              )",
            [$tenantId, $meetingId, $meetingId, $meetingId]
        ) ?? 0);
    }

    /**
     * Delete audit events for a meeting (reset demo, best-effort).
     */
    public function deleteByMeeting(string $meetingId, string $tenantId): void
    {
        try {
            $this->execute(
                "DELETE FROM audit_events WHERE meeting_id = :mid AND tenant_id = :tid",
                [':mid' => $meetingId, ':tid' => $tenantId]
            );
        } catch (\Throwable $e) { /* table may not exist */ }
    }
}
