<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AuditEventRepository;

/**
 * Notifications controller.
 *
 * Provides in-app notifications derived from audit_events
 * and meeting lifecycle transitions.
 */
final class NotificationsController extends AbstractController {

    /** Actions that generate user-visible notifications. */
    private const NOTIF_ACTIONS = [
        'meeting_created', 'meeting_launched', 'meeting_closed',
        'meeting_validated', 'motion_opened', 'motion_closed',
        'attendances_bulk_update', 'member_created', 'vote_anomaly',
    ];

    /**
     * GET /api/v1/notifications
     *
     * Returns recent audit events as notifications for the current user.
     */
    public function list(): void {
        $tenantId = api_current_tenant_id();
        $limit    = min(max((int) (api_query('limit') ?: 20), 1), 50);

        $auditRepo = new AuditEventRepository();
        $rows = $auditRepo->listRecentByActions($tenantId, self::NOTIF_ACTIONS, $limit);

        api_ok([
            'notifications' => $rows,
            'unread_count'  => count($rows),
        ]);
    }

    /**
     * PUT /api/v1/notifications_read
     *
     * Acknowledge notifications (no-op until notification_reads table
     * is added via a proper migration).
     */
    public function markRead(): void {
        api_ok(['marked' => 0]);
    }
}
