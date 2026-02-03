<?php
declare(strict_types=1);

/**
 * GET /api/v1/meeting_workflow_check.php?meeting_id=UUID&to_status=STATUS
 *
 * Check pre-conditions before a meeting state transition.
 * Returns issues (blocking) and warnings (non-blocking).
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\MeetingWorkflowService;

api_require_role(['operator', 'president', 'admin', 'viewer']);

$meetingId = api_require_uuid($_GET, 'meeting_id');
$toStatus = trim((string)($_GET['to_status'] ?? ''));

$tenantId = api_current_tenant_id();

if ($toStatus === '') {
    // Return readiness for all possible transitions
    $result = MeetingWorkflowService::getTransitionReadiness($meetingId, $tenantId);
    api_ok($result);
} else {
    // Check specific transition
    $result = MeetingWorkflowService::issuesBeforeTransition($meetingId, $tenantId, $toStatus);
    api_ok([
        'to_status' => $toStatus,
        'can_proceed' => $result['can_proceed'],
        'issues' => $result['issues'],
        'warnings' => $result['warnings'],
    ]);
}
