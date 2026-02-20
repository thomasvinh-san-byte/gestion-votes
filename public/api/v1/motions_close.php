<?php
// public/api/v1/motions_close.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Service\OfficialResultsService;
use AgVote\Service\VoteTokenService;
use AgVote\WebSocket\EventBroadcaster;

api_require_role(['operator', 'president', 'admin']);

$input = api_request('POST');

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    api_fail('invalid_motion_id', 422);
    exit;
}

try {
    $repo = new MotionRepository();

    // SECURITY: Use transaction + lock to prevent race conditions
    db()->beginTransaction();

    // Load motion with FOR UPDATE lock to prevent concurrent modifications
    $motion = $repo->findByIdForTenantForUpdate($motionId, api_current_tenant_id());
    if (!$motion) {
        db()->rollBack();
        api_fail('motion_not_found', 404);
        exit;
    }

    if (empty($motion['opened_at'])) {
        db()->rollBack();
        api_fail('motion_not_open', 409);
        exit;
    }
    if (!empty($motion['closed_at'])) {
        db()->rollBack();
        api_fail('motion_already_closed', 409);
        exit;
    }

    $repo->markClosed($motionId, api_current_tenant_id());

    // Compute/freeze results (official) — schema already ensured above
    $o = OfficialResultsService::computeOfficialTallies((string)$motionId);
    $repo->updateOfficialResults(
        (string)$motionId,
        $o['source'], $o['for'], $o['against'],
        $o['abstain'], $o['total'], $o['decision'], $o['reason']
    );

    db()->commit();

    // Post-commit side-effects: wrap in try-catch so a failure here
    // does NOT return 500 when the motion was already successfully closed.
    try {
        VoteTokenService::revokeForMotion($motionId);
    } catch (Throwable $tokenErr) {
        error_log('[motions_close] token revocation failed after commit: ' . $tokenErr->getMessage());
    }

    try {
        audit_log('motion_closed', 'motion', $motionId, [
            'meeting_id' => (string)$motion['meeting_id'],
        ]);
    } catch (Throwable $auditErr) {
        error_log('[motions_close] audit_log failed after commit: ' . $auditErr->getMessage());
    }

    try {
        EventBroadcaster::motionClosed((string)$motion['meeting_id'], $motionId, [
            'for' => $o['for'] ?? 0,
            'against' => $o['against'] ?? 0,
            'abstain' => $o['abstain'] ?? 0,
            'total' => $o['total'] ?? 0,
            'decision' => $o['decision'] ?? 'unknown',
            'reason' => $o['reason'] ?? null,
        ]);
    } catch (Throwable $wsErr) {
        error_log('[motions_close] EventBroadcaster failed after commit: ' . $wsErr->getMessage());
    }

    // Include vote completeness info so operator can see if votes are missing
    $eligibleCount = 0;
    try {
        $attendanceRepo = new AttendanceRepository();
        $eligibleCount = $attendanceRepo->countByModes(
            (string)$motion['meeting_id'],
            api_current_tenant_id(),
            ['present', 'remote']
        );
    } catch (Throwable $e) { /* non-critical */ }

    api_ok([
        'meeting_id'       => (string)$motion['meeting_id'],
        'closed_motion_id' => $motionId,
        'results'          => [
            'for'      => $o['for'] ?? 0,
            'against'  => $o['against'] ?? 0,
            'abstain'  => $o['abstain'] ?? 0,
            'total'    => $o['total'] ?? 0,
            'decision' => $o['decision'] ?? 'unknown',
            'reason'   => $o['reason'] ?? null,
        ],
        'eligible_count'   => $eligibleCount,
        'votes_cast'       => $o['total'] ?? 0,
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    api_fail('motion_close_failed', 500, ['detail' => $e->getMessage()]);
}
