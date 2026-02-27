<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AuditRepository;

/**
 * Notifications controller.
 *
 * Provides in-app notifications derived from audit events
 * and meeting lifecycle transitions.
 */
final class NotificationsController extends AbstractController {

    /**
     * GET /api/v1/notifications
     *
     * Returns recent notifications for the current user.
     * Notifications are derived from audit_log events relevant
     * to the user's role and tenant.
     */
    public function list(): void {
        $tenantId = api_current_tenant_id();
        $userId   = api_current_user_id();
        $limit    = (int) (api_query('limit') ?: 20);
        $limit    = min(max($limit, 1), 50);

        $pdo = \AgVote\Core\Providers\DatabaseProvider::get();

        // Fetch recent audit events as notifications
        $sql = <<<'SQL'
            SELECT
                al.id,
                al.action        AS type,
                al.detail        AS message,
                al.created_at    AS timestamp,
                al.user_id       AS actor_id,
                COALESCE(u.display_name, u.email, 'Système') AS actor_name,
                CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN notification_reads nr
                ON nr.audit_log_id = al.id AND nr.user_id = :user_id
            WHERE al.tenant_id = :tenant_id
              AND al.action IN (
                  'meeting.created', 'meeting.launched', 'meeting.closed',
                  'meeting.validated', 'motion.opened', 'motion.closed',
                  'attendance.bulk_import', 'member.created', 'vote.anomaly'
              )
            ORDER BY al.created_at DESC
            LIMIT :lim
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $unread = 0;
        foreach ($rows as &$row) {
            $row['is_read'] = (bool) $row['is_read'];
            if (!$row['is_read']) {
                $unread++;
            }
        }
        unset($row);

        api_ok([
            'notifications' => $rows,
            'unread_count'  => $unread,
        ]);
    }

    /**
     * PUT /api/v1/notifications_read
     *
     * Mark all notifications as read for the current user.
     */
    public function markRead(): void {
        $tenantId = api_current_tenant_id();
        $userId   = api_current_user_id();

        $pdo = \AgVote\Core\Providers\DatabaseProvider::get();

        // Create notification_reads table if not exists (idempotent)
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notification_reads (
                id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                user_id    CHAR(36) NOT NULL,
                audit_log_id CHAR(36) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_audit (user_id, audit_log_id)
            )
        SQL);

        // Mark all unread notifications as read
        $sql = <<<'SQL'
            INSERT IGNORE INTO notification_reads (user_id, audit_log_id)
            SELECT :user_id, al.id
            FROM audit_log al
            WHERE al.tenant_id = :tenant_id
              AND al.action IN (
                  'meeting.created', 'meeting.launched', 'meeting.closed',
                  'meeting.validated', 'motion.opened', 'motion.closed',
                  'attendance.bulk_import', 'member.created', 'vote.anomaly'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM notification_reads nr
                  WHERE nr.audit_log_id = al.id AND nr.user_id = :user_id2
              )
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':user_id2', $userId);
        $stmt->bindValue(':tenant_id', $tenantId);
        $stmt->execute();

        api_ok(['marked' => $stmt->rowCount()]);
    }
}
