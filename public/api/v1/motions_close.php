<?php
// public/api/v1/motions_close.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Service\OfficialResultsService;
use AgVote\WebSocket\EventBroadcaster;

api_require_role(['operator', 'president', 'admin']);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
    exit;
}

$input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId === '' || !api_is_uuid($motionId)) {
    api_fail('invalid_motion_id', 422);
    exit;
}

try {
    $repo = new MotionRepository();

    // Ensure official columns exist BEFORE transaction (DDL can't be in a TX)
    OfficialResultsService::ensureSchema();

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

    api_ok([
        'meeting_id'       => (string)$motion['meeting_id'],
        'closed_motion_id' => $motionId,
    ]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    api_fail('motion_close_failed', 500, ['detail' => $e->getMessage()]);
}
