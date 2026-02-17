<?php
/**
 * POST /api/v1/meeting_launch.php
 *
 * Atomic multi-step transition: current_status → … → live
 * Replaces the frontend loop that called meeting_transition.php
 * multiple times, which could leave the meeting in an intermediate state.
 */
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingWorkflowService;
use AgVote\WebSocket\EventBroadcaster;

$input = api_request('POST');

api_require_role(['operator', 'president', 'admin']);

$meetingId = api_require_uuid($input, 'meeting_id');

$repo = new MeetingRepository();
$tenant = api_current_tenant_id();
$userId = api_current_user_id();

// Lock row to prevent concurrent launches
$pdo = db();
$pdo->beginTransaction();

try {
    $meeting = $repo->lockForUpdate($meetingId, $tenant);

    if (!$meeting) {
        $pdo->rollBack();
        api_fail('meeting_not_found', 404);
    }

    $fromStatus = $meeting['status'];

    // Determine required transitions to reach 'live'
    $path = [];
    switch ($fromStatus) {
        case 'draft':     $path = ['scheduled', 'frozen', 'live']; break;
        case 'scheduled': $path = ['frozen', 'live']; break;
        case 'frozen':    $path = ['live']; break;
        case 'live':
            $pdo->rollBack();
            api_fail('already_in_status', 422, ['detail' => 'La séance est déjà en cours.']);
            break;
        default:
            $pdo->rollBack();
            api_fail('invalid_launch_status', 422, [
                'detail' => "Impossible de lancer depuis le statut '$fromStatus'.",
            ]);
    }

    // Validate workflow prerequisites for the final state (live)
    $workflowCheck = MeetingWorkflowService::issuesBeforeTransition($meetingId, $tenant, 'live');
    if (!$workflowCheck['can_proceed']) {
        $pdo->rollBack();
        api_fail('workflow_issues', 422, [
            'detail' => 'Lancement bloqué par des pré-requis',
            'issues' => $workflowCheck['issues'],
            'warnings' => $workflowCheck['warnings'],
        ]);
    }

    // Execute each transition within the same transaction
    $now = date('Y-m-d H:i:s');
    $currentStatus = $fromStatus;

    foreach ($path as $toStatus) {
        // Verify each transition is allowed by the state machine
        AuthMiddleware::requireTransition($currentStatus, $toStatus, $meetingId);

        $fields = ['status' => $toStatus];

        switch ($toStatus) {
            case 'scheduled':
                break;
            case 'frozen':
                $fields['frozen_at'] = $now;
                $fields['frozen_by'] = $userId;
                break;
            case 'live':
                if (empty($meeting['started_at'])) {
                    $fields['started_at'] = $now;
                }
                if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
                    $fields['scheduled_at'] = $now;
                }
                $fields['opened_by'] = $userId;
                break;
        }

        $repo->updateFields($meetingId, $tenant, $fields);
        $currentStatus = $toStatus;
    }

    // Audit log for the full transition
    audit_log('meeting.launch', 'meeting', $meetingId, [
        'from_status' => $fromStatus,
        'to_status'   => 'live',
        'path'        => $path,
        'title'       => $meeting['title'],
    ], $meetingId);

    $pdo->commit();

    // Broadcast after commit
    try {
        EventBroadcaster::meetingStatusChanged($meetingId, $tenant, 'live', $fromStatus);
    } catch (\Throwable $e) {
        // Don't fail if broadcast fails
    }

    api_ok([
        'meeting_id'      => $meetingId,
        'from_status'     => $fromStatus,
        'to_status'       => 'live',
        'path'            => $path,
        'transitioned_at' => date('c'),
        'warnings'        => $workflowCheck['warnings'] ?? [],
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
