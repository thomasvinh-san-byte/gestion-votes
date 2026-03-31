<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Notifications controller.
 *
 * Provides in-app notifications derived from audit_events
 * and meeting lifecycle transitions. Tracks read state per user
 * via the notification_reads table.
 */
final class NotificationsController extends AbstractController {

    /** Actions that generate user-visible notifications. */
    private const NOTIF_ACTIONS = [
        'meeting_created', 'meeting_launched', 'meeting_closed',
        'meeting_validated', 'motion_opened', 'motion_closed',
        'attendances_bulk_update', 'member_created', 'vote_anomaly',
        'meeting_archived', 'member_imported', 'proxy_created',
        'emergency_triggered', 'speech_granted',
    ];

    /**
     * GET /api/v1/notifications
     *
     * Returns recent audit events as notifications for the current user.
     * Includes read/unread state.
     */
    public function list(): void {
        $tenantId = api_current_tenant_id();
        $userId   = api_current_user_id();
        $limit    = min(max((int) (api_query('limit') ?: 20), 1), 50);

        $auditRepo = $this->repo()->auditEvent();
        $rows = $auditRepo->listRecentByActions($tenantId, self::NOTIF_ACTIONS, $limit);

        // Fetch read event IDs for this user
        $readIds = [];
        try {
            $pdo = $this->repo()->auditEvent()->getPdo();
            $st = $pdo->prepare(
                'SELECT event_id FROM notification_reads WHERE user_id = :uid AND tenant_id = :tid'
            );
            $st->execute([':uid' => $userId, ':tid' => $tenantId]);
            $readIds = array_column($st->fetchAll(\PDO::FETCH_ASSOC), 'event_id');
        } catch (\Throwable $e) {
            // Table may not exist yet (migration not run) — treat all as unread
        }

        $unreadCount = 0;
        foreach ($rows as &$row) {
            $row['read'] = in_array($row['id'] ?? '', $readIds, true);
            if (!$row['read']) {
                $unreadCount++;
            }
        }
        unset($row);

        api_ok([
            'notifications' => $rows,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * PUT /api/v1/notifications_read
     *
     * Mark notifications as read. Accepts { ids: ["event-id-1", ...] }
     * or { all: true } to mark everything as read.
     */
    public function markRead(): void {
        $tenantId = api_current_tenant_id();
        $userId   = api_current_user_id();
        $body     = api_request('PUT', 'POST');

        try {
            $pdo = $this->repo()->auditEvent()->getPdo();

            if (!empty($body['all'])) {
                // Mark all current notifications as read
                $auditRepo = $this->repo()->auditEvent();
                $rows = $auditRepo->listRecentByActions($tenantId, self::NOTIF_ACTIONS, 50);
                $ids = array_column($rows, 'id');
            } else {
                $ids = $body['ids'] ?? [];
            }

            if (!is_array($ids) || empty($ids)) {
                api_ok(['marked' => 0]);
                return;
            }

            $marked = 0;
            $st = $pdo->prepare(
                'INSERT INTO notification_reads (user_id, tenant_id, event_id, read_at)
                 VALUES (:uid, :tid, :eid, NOW())
                 ON CONFLICT (user_id, event_id) DO NOTHING'
            );

            foreach ($ids as $eventId) {
                $st->execute([':uid' => $userId, ':tid' => $tenantId, ':eid' => (string) $eventId]);
                $marked += $st->rowCount();
            }

            api_ok(['marked' => $marked]);
        } catch (\Throwable $e) {
            // Migration not yet run — graceful fallback
            api_ok(['marked' => 0]);
        }
    }
}
