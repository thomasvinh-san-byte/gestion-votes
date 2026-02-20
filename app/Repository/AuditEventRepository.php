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
}
